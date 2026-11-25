/*
 * Sygma Solar Suite – 3D roof designer.
 *
 * Plan coordinates are metres: x = east, y = north (north is up on the plan).
 * three.js coordinates: x = east, y = up, z = south (z = -north).
 */
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/OrbitControls.js';

const INIT = JSON.parse(document.getElementById('rd-init').textContent);
const $ = (id) => document.getElementById(id);
const D2R = Math.PI / 180;
const R2D = 180 / Math.PI;
const DIRECT_SHARE = 0.8;          // share of yearly sunlight that is direct (shadows remove only this part)
const MONTH_START = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const DEFAULT_LAT = 13.0;          // used for the sun only until the site location is set

// ------------------------------------------------------------------ state
const saved = INIT.data || {};
const S = {
    roof: saved.roof || { pts: rectPts(12, 10), h: 6, parapet: 0.9 },
    obs: saved.obs || [],
    p: Object.assign({ itemId: INIT.panels[0] ? INIT.panels[0].id : null, watt: INIT.panels[0] ? INIT.panels[0].watt : 545,
        len: 2.278, wid: 1.134, orient: 'portrait', tilt: 10, az: 180, front: 0.6, setback: 0.6, clear: 0.5, gapAuto: true, gap: 0.8 }, saved.p || {}),
    removed: new Set(saved.removed || []),
    sun: Object.assign({ month: 11, hour: 10 }, saved.sun || {}),
    panels: [],
    sim: saved.sim ? Object.assign(saved.sim, { stale: false }) : null,
    yield: null,
    selected: null,
    bg: saved.bg || null,              // top-view photo under the plan
    google: saved.google || null,      // last Google Solar roof detection (facts only)
    photos: (INIT.photos || []).slice(),
    scalePts: [],
    mode: 'select',
    drawPts: [],
    dirty: false,
};
let nextObsId = S.obs.reduce((m, o) => Math.max(m, o.id || 0), 0) + 1;

function rectPts(w, d) { return [[-w / 2, -d / 2], [w / 2, -d / 2], [w / 2, d / 2], [-w / 2, d / 2]]; }
function lShapePts(w, d) {
    return [[-w / 2, -d / 2], [w / 2, -d / 2], [w / 2, 0], [0, 0], [0, d / 2], [-w / 2, d / 2]];
}

// ------------------------------------------------------------------ geometry
const dot = (a, b) => a[0] * b[0] + a[1] * b[1];
function polyArea(p) { let a = 0; for (let i = 0; i < p.length; i++) { const q = p[(i + 1) % p.length]; a += p[i][0] * q[1] - q[0] * p[i][1]; } return a / 2; }
function centroid(p) { let x = 0, y = 0; p.forEach((q) => { x += q[0]; y += q[1]; }); return [x / p.length, y / p.length]; }
function inside(pt, poly) {
    let c = false;
    for (let i = 0, j = poly.length - 1; i < poly.length; j = i++) {
        const [xi, yi] = poly[i], [xj, yj] = poly[j];
        if ((yi > pt[1]) !== (yj > pt[1]) && pt[0] < ((xj - xi) * (pt[1] - yi)) / (yj - yi) + xi) c = !c;
    }
    return c;
}
function distSeg(p, a, b) {
    const dx = b[0] - a[0], dy = b[1] - a[1], L = dx * dx + dy * dy;
    let t = L ? ((p[0] - a[0]) * dx + (p[1] - a[1]) * dy) / L : 0; t = Math.max(0, Math.min(1, t));
    return Math.hypot(p[0] - a[0] - t * dx, p[1] - a[1] - t * dy);
}
function minEdgeDist(p, poly) { let m = Infinity; for (let i = 0; i < poly.length; i++) m = Math.min(m, distSeg(p, poly[i], poly[(i + 1) % poly.length])); return m; }
/** Separating-axis test for two convex polygons. */
function overlap(A, B) {
    for (const P of [A, B]) {
        for (let i = 0; i < P.length; i++) {
            const a = P[i], b = P[(i + 1) % P.length], n = [b[1] - a[1], a[0] - b[0]];
            let minA = Infinity, maxA = -Infinity, minB = Infinity, maxB = -Infinity;
            A.forEach((q) => { const v = dot(q, n); minA = Math.min(minA, v); maxA = Math.max(maxA, v); });
            B.forEach((q) => { const v = dot(q, n); minB = Math.min(minB, v); maxB = Math.max(maxB, v); });
            if (maxA < minB - 1e-9 || maxB < minA - 1e-9) return false;
        }
    }
    return true;
}
function hull(points) {
    const p = points.slice().sort((a, b) => a[0] - b[0] || a[1] - b[1]);
    if (p.length < 3) return p;
    const cross = (o, a, b) => (a[0] - o[0]) * (b[1] - o[1]) - (a[1] - o[1]) * (b[0] - o[0]);
    const lo = [], up = [];
    for (const q of p) { while (lo.length >= 2 && cross(lo[lo.length - 2], lo[lo.length - 1], q) <= 0) lo.pop(); lo.push(q); }
    for (const q of p.slice().reverse()) { while (up.length >= 2 && cross(up[up.length - 2], up[up.length - 1], q) <= 0) up.pop(); up.push(q); }
    return lo.slice(0, -1).concat(up.slice(0, -1));
}
function obsPoly(o) {
    if (o.type === 'room') {
        const c = Math.cos((o.rot || 0) * D2R), s = Math.sin((o.rot || 0) * D2R);
        return [[-o.w / 2, -o.d / 2], [o.w / 2, -o.d / 2], [o.w / 2, o.d / 2], [-o.w / 2, o.d / 2]].map(([x, y]) => [o.x + x * c - y * s, o.y + x * s + y * c]);
    }
    const r = o.w / 2, out = [];
    for (let i = 0; i < 16; i++) { const a = (i / 16) * Math.PI * 2; out.push([o.x + r * Math.cos(a), o.y + r * Math.sin(a)]); }
    return out;
}
/** Height of an obstacle's top above the roof (trees stand on the ground). */
function obsTop(o) { return o.type === 'tree' ? o.h - S.roof.h : o.h; }

// ------------------------------------------------------------------ sun
function lat() { const v = parseFloat($('dLat').value); return Number.isFinite(v) ? v : DEFAULT_LAT; }
/** Sun altitude / azimuth (degrees; azimuth from north, clockwise) on the 21st of a month at local solar time. */
function sunPos(month, hour, latitude = lat()) {
    const n = MONTH_START[month] + 21;
    const decl = 23.44 * Math.sin((2 * Math.PI * (284 + n)) / 365) * D2R;
    const H = 15 * (hour - 12) * D2R, phi = latitude * D2R;
    const alt = Math.asin(Math.sin(phi) * Math.sin(decl) + Math.cos(phi) * Math.cos(decl) * Math.cos(H));
    const az = Math.atan2(Math.sin(H), Math.cos(H) * Math.sin(phi) - Math.tan(decl) * Math.cos(phi)) + Math.PI;
    return { alt: alt * R2D, az: (az * R2D + 360) % 360 };
}
/** Unit vector towards the sun in three.js coordinates. */
function sunVec3(alt, az) { return new THREE.Vector3(Math.sin(az * D2R) * Math.cos(alt * D2R), Math.sin(alt * D2R), -Math.cos(az * D2R) * Math.cos(alt * D2R)); }
/** Horizontal shadow displacement (plan metres) per metre of height. */
function shadowStep(alt, az) { const k = 1 / Math.tan(Math.max(alt, 1) * D2R); return [-Math.sin(az * D2R) * k, -Math.cos(az * D2R) * k]; }

// ------------------------------------------------------------------ panel layout
function panelDims() {
    const p = S.p, portrait = p.orient === 'portrait';
    const a = portrait ? p.wid : p.len;           // along the row
    const s = portrait ? p.len : p.wid;           // up the slope
    const t = p.tilt * D2R;
    return { a, s, t, d: s * Math.cos(t), rise: s * Math.sin(t) };
}
function frame() {
    const A = S.p.az * D2R;
    return { r: [Math.cos(A), -Math.sin(A)], b: [-Math.sin(A), -Math.cos(A)], f: [Math.sin(A), Math.cos(A)] };
}
/** Row gap so the back row is not shaded 9 AM–3 PM on the shortest day. */
function autoGap(rise) {
    if (rise < 0.01) return 0.1;
    const { b } = frame();
    const month = lat() >= 0 ? 11 : 5;
    let need = 0;
    for (let h = 9; h <= 15; h += 0.5) {
        const sp = sunPos(month, h);
        if (sp.alt <= 2) continue;
        const st = shadowStep(sp.alt, sp.az);
        need = Math.max(need, rise * dot(st, b));
    }
    return Math.min(6, Math.max(0.1, need));
}
function rowGap() { const { rise } = panelDims(); return S.p.gapAuto ? autoGap(rise) : Math.max(0, +S.p.gap || 0); }

function layout() {
    const roof = S.roof.pts;
    if (roof.length < 3) { S.panels = []; return; }
    const { a, d } = panelDims();
    const { r, b } = frame();
    const setback = Math.max(0, +S.p.setback || 0), clear = Math.max(0, +S.p.clear || 0);
    const pitch = d + rowGap(), step = a + 0.02;
    const loc = roof.map((P) => [dot(P, r), dot(P, b)]);
    const umin = Math.min(...loc.map((q) => q[0])), umax = Math.max(...loc.map((q) => q[0]));
    const vmin = Math.min(...loc.map((q) => q[1])), vmax = Math.max(...loc.map((q) => q[1]));
    const obstacles = S.obs.filter((o) => obsTop(o) > 0.05).map(obsPoly);
    const toW = (u, v) => [u * r[0] + v * b[0], u * r[1] + v * b[1]];
    const valid = (u0, v0) => {
        const C = [toW(u0, v0), toW(u0 + a, v0), toW(u0 + a, v0 + d), toW(u0, v0 + d)];
        for (const c of C) { if (!inside(c, roof) || minEdgeDist(c, roof) < setback - 1e-6) return null; }
        for (const q of loc) { if (q[0] > u0 - setback + 1e-6 && q[0] < u0 + a + setback - 1e-6 && q[1] > v0 - setback + 1e-6 && q[1] < v0 + d + setback - 1e-6) return null; }
        if (obstacles.length) {
            const E = [toW(u0 - clear, v0 - clear), toW(u0 + a + clear, v0 - clear), toW(u0 + a + clear, v0 + d + clear), toW(u0 - clear, v0 + d + clear)];
            for (const O of obstacles) if (overlap(E, O)) return null;
        }
        return C;
    };
    let best = [];
    for (const ou of [0, 0.25, 0.5, 0.75]) {
        for (const ov of [0, 1 / 3, 2 / 3]) {
            const list = [];
            for (let v0 = vmin + setback + ov * pitch - pitch; v0 + d <= vmax - setback + 1e-6; v0 += pitch) {
                if (v0 < vmin + setback - 1e-6) continue;
                for (let u0 = umin + setback + ou * step - step; u0 + a <= umax - setback + 1e-6; u0 += step) {
                    if (u0 < umin + setback - 1e-6) continue;
                    const C = valid(u0, v0);
                    if (!C) continue;
                    const c = toW(u0 + a / 2, v0 + d / 2);
                    list.push({ key: c[0].toFixed(1) + ',' + c[1].toFixed(1), c, corners: C, front: v0 });
                }
            }
            if (list.length > best.length) best = list;
        }
    }
    S.panels = best;
}
const activePanels = () => S.panels.filter((p) => !S.removed.has(p.key));

// ------------------------------------------------------------------ 2D plan
const NS = 'http://www.w3.org/2000/svg';
const svg = document.createElementNS(NS, 'svg');
$('plan').appendChild(svg);
const el = (tag, attrs = {}, parent = svg) => { const e = document.createElementNS(NS, tag); for (const k in attrs) e.setAttribute(k, attrs[k]); parent.appendChild(e); return e; };
const ptsAttr = (pts) => pts.map((q) => q[0].toFixed(3) + ',' + (-q[1]).toFixed(3)).join(' ');
let view = null;

function fitView() {
    const all = S.roof.pts.concat(...S.obs.map(obsPoly), bgCorners());
    const xs = all.map((q) => q[0]), ys = all.map((q) => q[1]);
    const x0 = Math.min(...xs) - 3, x1 = Math.max(...xs) + 3, y0 = Math.min(...ys) - 3, y1 = Math.max(...ys) + 3;
    view = [x0, -y1, x1 - x0, y1 - y0];
    svg.setAttribute('viewBox', view.join(' '));
    svg.setAttribute('preserveAspectRatio', 'xMidYMid meet');
}

function heatColour(loss) {
    if (loss == null) return null;
    const t = Math.max(0, Math.min(1, loss / 30));
    const h = 130 - 130 * t;
    return `hsl(${Math.round(h)}, 75%, 42%)`;
}

function renderPlan() {
    if (!view) fitView();
    svg.innerHTML = '';
    const [vx, vy, vw, vh] = view;
    const defs = el('defs');
    const clip = el('clipPath', { id: 'roofClip' }, defs);
    if (S.roof.pts.length >= 3) el('polygon', { points: ptsAttr(S.roof.pts) }, clip);
    // top-view photo
    const bgOn = S.bg && !S.bg.hidden && bgUrl();
    $('plan').classList.toggle('has-bg', !!bgOn);
    if (bgOn) {
        const b = S.bg;
        el('image', { href: bgUrl(), x: 0, y: 0, width: b.w, height: b.h, preserveAspectRatio: 'none', class: 'bg-photo', opacity: b.opacity ?? 0.75,
            transform: `translate(${b.cx} ${-b.cy}) rotate(${-(b.rot || 0)}) scale(${b.mpp}) translate(${-b.w / 2} ${-b.h / 2})` });
    }
    // grid (1 m, every 5 m darker)
    const g = el('g', { class: 'grid' });
    for (let x = Math.floor(vx); x <= vx + vw; x++) el('line', { x1: x, y1: vy, x2: x, y2: vy + vh, class: x % 5 ? '' : 'major' }, g);
    for (let y = Math.floor(vy); y <= vy + vh; y++) el('line', { x1: vx, y1: y, x2: vx + vw, y2: y, class: y % 5 ? '' : 'major' }, g);
    // roof
    if (S.roof.pts.length >= 3) el('polygon', { points: ptsAttr(S.roof.pts), class: 'roof' });
    // shadows at the chosen month / time
    const sp = sunPos(+S.sun.month, +S.sun.hour);
    if (sp.alt > 1 && S.roof.pts.length >= 3) {
        const st = shadowStep(sp.alt, sp.az);
        const sh = el('g', { 'clip-path': 'url(#roofClip)' });
        const off = (q, h) => [q[0] + st[0] * h, q[1] + st[1] * h];
        const par = +S.roof.parapet || 0;
        if (par > 0) S.roof.pts.forEach((q, i) => { const n = S.roof.pts[(i + 1) % S.roof.pts.length]; el('polygon', { points: ptsAttr(hull([q, n, off(q, par), off(n, par)])), class: 'shadow' }, sh); });
        S.obs.forEach((o) => { const top = obsTop(o); if (top <= 0) return; const P = obsPoly(o); el('polygon', { points: ptsAttr(hull(P.concat(P.map((q) => off(q, top))))), class: 'shadow' }, sh); });
        const { rise } = panelDims(), front = +S.p.front || 0;
        activePanels().forEach((p) => {
            const [c0, c1, c2, c3] = p.corners;
            el('polygon', { points: ptsAttr(hull([off(c0, front), off(c1, front), off(c2, front + rise), off(c3, front + rise)])), class: 'shadow' }, sh);
        });
    }
    // panels
    const heat = $('heatOn').checked && S.sim && !S.sim.stale;
    S.panels.forEach((p) => {
        const off = S.removed.has(p.key);
        const e = el('polygon', { points: ptsAttr(p.corners), class: 'panel' + (off ? ' off' : ''), 'data-panel': p.key });
        if (heat && !off) { const c = heatColour(S.sim.perPanel[p.key]); if (c) e.style.fill = c; }
    });
    // obstacles
    S.obs.forEach((o) => {
        const P = obsPoly(o);
        el('polygon', { points: ptsAttr(P), class: 'obs' + (o.type === 'tree' ? ' tree' : '') + (S.selected === o.id ? ' sel' : ''), 'data-obs': o.id });
        const t = el('text', { x: o.x, y: -o.y, 'text-anchor': 'middle', 'dominant-baseline': 'middle', class: 'obs-label' });
        t.textContent = o.h + ' m';
    });
    // roof corners + edge lengths
    if (S.mode !== 'draw' && INIT.canEdit) {
        S.roof.pts.forEach((q, i) => {
            const n = S.roof.pts[(i + 1) % S.roof.pts.length];
            const L = Math.hypot(n[0] - q[0], n[1] - q[1]);
            const mid = [(q[0] + n[0]) / 2, (q[1] + n[1]) / 2], c = centroid(S.roof.pts);
            const away = [mid[0] - c[0], mid[1] - c[1]], k = 0.6 / (Math.hypot(...away) || 1);
            const t = el('text', { x: mid[0] + away[0] * k, y: -(mid[1] + away[1] * k), 'text-anchor': 'middle', 'dominant-baseline': 'middle', class: 'edge-label', 'data-edge': i });
            t.textContent = L.toFixed(2) + ' m';
        });
        S.roof.pts.forEach((q, i) => el('circle', { cx: q[0], cy: -q[1], r: 0.28, class: 'vh', 'data-vertex': i }));
    }
    // outline being drawn
    if (S.mode === 'draw' && S.drawPts.length) {
        el('polyline', { points: ptsAttr(S.drawPts), class: 'roof drawing', fill: 'none' });
        S.drawPts.forEach((q) => el('circle', { cx: q[0], cy: -q[1], r: 0.18, class: 'draw-pt' }));
    }
    // scale being set on the photo
    if (S.mode === 'scale' && S.scalePts.length) {
        if (S.scalePts.length === 2) el('line', { x1: S.scalePts[0][0], y1: -S.scalePts[0][1], x2: S.scalePts[1][0], y2: -S.scalePts[1][1], class: 'scale-line' });
        S.scalePts.forEach((q) => el('circle', { cx: q[0], cy: -q[1], r: 0.2, class: 'scale-pt' }));
    }
    // north arrow + scale
    const nx = vx + vw - 1.2, ny = vy + 1.4;
    el('path', { d: `M${nx},${ny - 0.9} l0.4,1.2 l-0.4,-0.35 l-0.4,0.35z`, fill: '#ef4444' });
    const nt = el('text', { x: nx, y: ny + 0.9, 'text-anchor': 'middle', class: 'north' }); nt.textContent = 'N';
    const sx = vx + 0.8, sy = vy + vh - 0.8;
    el('line', { x1: sx, y1: sy, x2: sx + 5, y2: sy, stroke: '#475569', 'stroke-width': 0.08 });
    const sc = el('text', { x: sx + 2.5, y: sy - 0.3, 'text-anchor': 'middle', class: 'scale' }); sc.textContent = '5 m';
}

// --- plan interaction
function toPlan(evt) {
    const pt = svg.createSVGPoint(); pt.x = evt.clientX; pt.y = evt.clientY;
    const w = pt.matrixTransform(svg.getScreenCTM().inverse());
    const snap = (v) => Math.round(v / 0.05) * 0.05;
    return [snap(w.x), snap(-w.y)];
}
let drag = null;
svg.addEventListener('pointerdown', (e) => {
    if (!INIT.canEdit) return;
    const t = e.target;
    if (S.mode === 'draw' || S.mode === 'scale') return;
    if (S.mode === 'movebg' && S.bg) { const p = toPlan(e); drag = { kind: 'bg', dx: S.bg.cx - p[0], dy: S.bg.cy - p[1] }; svg.setPointerCapture(e.pointerId); e.preventDefault(); return; }
    if (t.dataset.vertex !== undefined) { drag = { kind: 'v', i: +t.dataset.vertex }; svg.setPointerCapture(e.pointerId); e.preventDefault(); }
    else if (t.dataset.obs !== undefined) {
        const o = S.obs.find((x) => x.id === +t.dataset.obs); S.selected = o.id;
        const p = toPlan(e); drag = { kind: 'o', o, dx: o.x - p[0], dy: o.y - p[1] }; svg.setPointerCapture(e.pointerId); renderObsList(); renderPlan(); e.preventDefault();
    }
});
svg.addEventListener('pointermove', (e) => {
    if (!drag) return;
    const p = toPlan(e);
    if (drag.kind === 'bg') { S.bg.cx = +(p[0] + drag.dx).toFixed(2); S.bg.cy = +(p[1] + drag.dy).toFixed(2); S.dirty = true; renderPlan(); return; }
    if (drag.kind === 'v') S.roof.pts[drag.i] = p;
    else { drag.o.x = +(p[0] + drag.dx).toFixed(2); drag.o.y = +(p[1] + drag.dy).toFixed(2); }
    changed({ geometry: true, quick: true });
});
svg.addEventListener('pointerup', () => { if (drag) { const k = drag.kind; drag = null; if (k === 'bg') { clearTimeout(rebuildTimer); rebuildTimer = setTimeout(build3d, 50); } else changed({ geometry: true }); } });
svg.addEventListener('click', (e) => {
    if (!INIT.canEdit) return;
    const t = e.target;
    if (S.mode === 'scale') { scaleClick(toPlan(e)); return; }
    if (S.mode === 'draw') {
        const p = toPlan(e);
        if (S.drawPts.length >= 3 && Math.hypot(p[0] - S.drawPts[0][0], p[1] - S.drawPts[0][1]) < 0.5) return finishDraw();
        S.drawPts.push(p); renderPlan(); return;
    }
    if (t.dataset.panel) { const k = t.dataset.panel; S.removed.has(k) ? S.removed.delete(k) : S.removed.add(k); changed({ panels: true }); }
    else if (t.dataset.edge !== undefined) {
        const i = +t.dataset.edge, P = S.roof.pts, q = P[i], n = P[(i + 1) % P.length];
        const L = Math.hypot(n[0] - q[0], n[1] - q[1]);
        const v = parseFloat(prompt('Length of this edge in metres', L.toFixed(2)));
        if (Number.isFinite(v) && v > 0.3) { P[(i + 1) % P.length] = [q[0] + ((n[0] - q[0]) / L) * v, q[1] + ((n[1] - q[1]) / L) * v]; view = null; changed({ geometry: true }); }
    } else if (t.dataset.obs === undefined && S.selected !== null) { S.selected = null; renderObsList(); renderPlan(); }
});
svg.addEventListener('dblclick', () => { if (S.mode === 'draw' && S.drawPts.length >= 3) finishDraw(); });
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && S.mode !== 'select') { S.mode = 'select'; S.drawPts = []; S.scalePts = []; $('plan').classList.remove('move-bg'); hint(''); renderPlan(); }
    if ((e.key === 'Delete' || e.key === 'Backspace') && S.selected !== null && document.activeElement === document.body) { S.obs = S.obs.filter((o) => o.id !== S.selected); S.selected = null; changed({ geometry: true }); }
});
function finishDraw() {
    if (S.drawPts.length >= 3) { S.roof.pts = polyArea(S.drawPts) < 0 ? S.drawPts.slice().reverse() : S.drawPts.slice(); }
    S.drawPts = []; S.mode = 'select'; hint(''); view = null; changed({ geometry: true });
}
function hint(t) { $('modeHint').textContent = t; }

// ------------------------------------------------------------------ obstacles list
const OBS_DEFAULT = { tank: { w: 1.2, d: 1.2, h: 2.0 }, room: { w: 3, d: 3, h: 2.8 }, tree: { w: 4, d: 4, h: 9 } };
const OBS_NAME = { tank: 'Water tank', room: 'Staircase room', tree: 'Tree / pole' };
document.querySelectorAll('[data-add]').forEach((b) => b.addEventListener('click', () => {
    const type = b.dataset.add, c = centroid(S.roof.pts), d = OBS_DEFAULT[type];
    const xs = S.roof.pts.map((q) => q[0]), ys = S.roof.pts.map((q) => q[1]);
    const x0 = Math.min(...xs), x1 = Math.max(...xs), y1 = Math.max(...ys);
    // Staircase room in the north-east corner, tanks along the north edge from the west, trees beside the building.
    const n = S.obs.filter((o) => o.type === type).length;
    const pos = type === 'tree' ? [x1 + 2.5, c[1] - n * 4]
        : type === 'room' ? [x1 - d.w / 2 - 0.6 - n * (d.w + 0.5), y1 - d.d / 2 - 0.6]
        : [x0 + 1.4 + n * 1.6, y1 - 1.4];
    if (!inside(pos, S.roof.pts) && type !== 'tree') { pos[0] = c[0]; pos[1] = c[1]; }
    const o = { id: nextObsId++, type, x: +pos[0].toFixed(2), y: +pos[1].toFixed(2), w: d.w, d: d.d, h: d.h, rot: 0 };
    S.obs.push(o); S.selected = o.id; view = null; changed({ geometry: true });
}));
function renderObsList() {
    const box = $('obsList'); box.innerHTML = '';
    if (!S.obs.length) { box.innerHTML = '<div class="text-muted">Nothing added. Water tanks, staircase rooms and trees cast shadows.</div>'; return; }
    const head = document.createElement('div'); head.className = 'obs-row text-muted'; head.innerHTML = '<span></span><span>W m</span><span>D m</span><span>H m</span><span></span>'; box.appendChild(head);
    S.obs.forEach((o) => {
        const row = document.createElement('div'); row.className = 'obs-row' + (S.selected === o.id ? ' sel' : '');
        const round = o.type !== 'room';
        row.innerHTML = `<span>${OBS_NAME[o.type]}${o.type === 'tree' ? ' <span class="text-muted">(from ground)</span>' : ''}</span>
            <input type="number" step="0.1" min="0.2" class="form-control" data-k="w" value="${o.w}" title="${round ? 'Diameter' : 'Width'}">
            <input type="number" step="0.1" min="0.2" class="form-control" data-k="d" value="${o.d}" ${round ? 'disabled' : ''} title="Depth">
            <input type="number" step="0.1" min="0.1" class="form-control" data-k="h" value="${o.h}" title="Height">
            <button type="button" class="btn btn-sm btn-link text-danger p-0" title="Remove"><i class="bi bi-x-lg"></i></button>`;
        row.querySelectorAll('input').forEach((inp) => inp.addEventListener('change', () => {
            const v = parseFloat(inp.value); if (!(v > 0)) return;
            o[inp.dataset.k] = v; if (round && inp.dataset.k === 'w') o.d = v; changed({ geometry: true });
        }));
        row.querySelector('button').addEventListener('click', () => { S.obs = S.obs.filter((x) => x !== o); changed({ geometry: true }); });
        row.addEventListener('click', () => { if (S.selected !== o.id) { S.selected = o.id; renderObsList(); renderPlan(); } });
        if (!INIT.canEdit) row.querySelectorAll('input,button').forEach((x) => { x.disabled = true; });
        box.appendChild(row);
    });
}

// ------------------------------------------------------------------ settings form
const sel = $('pSel');
INIT.panels.forEach((p) => { const o = new Option(`${p.name}`, p.id); o.dataset.watt = p.watt; sel.add(o); });
sel.add(new Option('Custom panel…', ''));
function dimsForWatt(w) { return w >= 500 ? [2.278, 1.134] : w >= 400 ? [1.722, 1.134] : [1.65, 0.992]; }
function loadForm(first = false) {
    if (first) { $('dLat').value = INIT.latitude ?? ''; $('dLng').value = INIT.longitude ?? ''; }
    $('rH').value = S.roof.h; $('rPar').value = S.roof.parapet;
    sel.value = S.p.itemId ? String(S.p.itemId) : '';
    $('pWatt').value = S.p.watt; $('pLen').value = S.p.len; $('pWid').value = S.p.wid; $('pOrient').value = S.p.orient;
    $('pTilt').value = S.p.tilt; $('pFront').value = S.p.front; $('pSetback').value = S.p.setback; $('pClear').value = S.p.clear;
    $('pGapAuto').checked = !!S.p.gapAuto; $('pGap').value = (+rowGap()).toFixed(2); $('pGap').disabled = !!S.p.gapAuto;
    const az = $('pAz'); if (![...az.options].some((o) => +o.value === +S.p.az)) az.add(new Option(S.p.az + '°', S.p.az)); az.value = String(S.p.az);
    $('sunMonth').value = S.sun.month; $('sunHour').value = S.sun.hour;
}
sel.addEventListener('change', () => {
    const o = sel.selectedOptions[0];
    S.p.itemId = sel.value ? +sel.value : null;
    if (o && o.dataset.watt) { S.p.watt = +o.dataset.watt; [S.p.len, S.p.wid] = dimsForWatt(S.p.watt); $('pWatt').value = S.p.watt; $('pLen').value = S.p.len; $('pWid').value = S.p.wid; }
    changed({ geometry: true });
});
const num = (id, fn) => $(id).addEventListener('change', () => { const v = parseFloat($(id).value); if (Number.isFinite(v)) { fn(v); changed({ geometry: true }); } });
num('rH', (v) => { S.roof.h = Math.max(2, v); });
num('rPar', (v) => { S.roof.parapet = Math.max(0, v); });
num('pWatt', (v) => { S.p.watt = v; });
num('pLen', (v) => { S.p.len = Math.max(0.5, v); });
num('pWid', (v) => { S.p.wid = Math.max(0.5, v); });
num('pTilt', (v) => { S.p.tilt = Math.max(0, Math.min(60, v)); fetchYield(); });
num('pFront', (v) => { S.p.front = Math.max(0, v); });
num('pSetback', (v) => { S.p.setback = Math.max(0, v); });
num('pClear', (v) => { S.p.clear = Math.max(0, v); });
num('pGap', (v) => { S.p.gap = Math.max(0, v); });
$('pOrient').addEventListener('change', () => { S.p.orient = $('pOrient').value; changed({ geometry: true }); });
$('pAz').addEventListener('change', () => { S.p.az = +$('pAz').value; fetchYield(); changed({ geometry: true }); });
$('pGapAuto').addEventListener('change', () => { S.p.gapAuto = $('pGapAuto').checked; $('pGap').disabled = S.p.gapAuto; changed({ geometry: true }); });
$('btnRefill').addEventListener('click', () => { S.removed.clear(); changed({ geometry: true }); });
['dLat', 'dLng'].forEach((id) => $(id).addEventListener('change', () => { fetchYield(); changed({ geometry: true }); }));
$('shapeRect').addEventListener('click', () => { S.roof.pts = rectPts(+$('rW').value || 12, +$('rD').value || 10); view = null; changed({ geometry: true }); });
$('shapeL').addEventListener('click', () => { S.roof.pts = lShapePts(+$('rW').value || 12, +$('rD').value || 10); view = null; changed({ geometry: true }); });
$('btnDraw').addEventListener('click', () => { switchView('plan'); S.mode = 'draw'; S.drawPts = []; hint('Click the roof corners. Click the first corner (or double-click) to finish. Esc cancels.'); renderPlan(); });
const rotate = (deg) => {
    const c = centroid(S.roof.pts), k = Math.cos(deg * D2R), s = Math.sin(deg * D2R);
    const rot = ([x, y]) => [c[0] + (x - c[0]) * k - (y - c[1]) * s, c[1] + (x - c[0]) * s + (y - c[1]) * k];
    S.roof.pts = S.roof.pts.map(rot);
    S.obs.forEach((o) => { [o.x, o.y] = rot([o.x, o.y]); o.rot = ((o.rot || 0) + deg) % 360; });
    view = null; changed({ geometry: true });
};
$('btnRotL').addEventListener('click', () => rotate(5));
$('btnRotR').addEventListener('click', () => rotate(-5));
$('btnLocate').addEventListener('click', () => {
    if (!navigator.geolocation) return alert('This browser cannot share its location.');
    $('btnLocate').disabled = true;
    navigator.geolocation.getCurrentPosition((pos) => {
        $('dLat').value = pos.coords.latitude.toFixed(6); $('dLng').value = pos.coords.longitude.toFixed(6);
        $('btnLocate').disabled = false; fetchYield(); changed({ geometry: true });
    }, (err) => { $('btnLocate').disabled = false; alert('Location not available: ' + err.message); }, { enableHighAccuracy: true, timeout: 15000 });
});
$('heatOn').addEventListener('change', () => { renderPlan(); build3d(); });

// sun controls
function sunLabel() {
    const sp = sunPos(+S.sun.month, +S.sun.hour);
    const h = Math.floor(S.sun.hour), m = Math.round((S.sun.hour - h) * 60);
    $('sunLabel').textContent = `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')} · sun ${sp.alt > 0 ? Math.round(sp.alt) + '° high' : 'below horizon'}`;
}
$('sunMonth').addEventListener('change', () => { S.sun.month = +$('sunMonth').value; sunLabel(); renderPlan(); updateSun(); });
$('sunHour').addEventListener('input', () => { S.sun.hour = +$('sunHour').value; sunLabel(); renderPlan(); updateSun(); });
let playTimer = null;
$('btnPlay').addEventListener('click', () => {
    if (playTimer) { clearInterval(playTimer); playTimer = null; $('btnPlay').innerHTML = '<i class="bi bi-play-fill"></i> Play day'; return; }
    $('btnPlay').innerHTML = '<i class="bi bi-pause-fill"></i> Pause';
    S.sun.hour = 6;
    playTimer = setInterval(() => {
        S.sun.hour = +S.sun.hour + 0.25; if (S.sun.hour > 18) S.sun.hour = 6;
        $('sunHour').value = S.sun.hour; sunLabel(); renderPlan(); updateSun();
    }, 120);
});

// ------------------------------------------------------------------ change handling
let rebuildTimer = null;
function changed({ geometry = false, panels = false, quick = false } = {}) {
    S.dirty = true;
    if (geometry) layout();
    if (geometry || panels) { if (S.sim) S.sim.stale = true; }
    if (!S.p.gapAuto) { /* keep */ } else $('pGap').value = rowGap().toFixed(2);
    renderPlan();
    if (!quick) { renderObsList(); results(); }
    clearTimeout(rebuildTimer); rebuildTimer = setTimeout(build3d, quick ? 250 : 50);
}

// ------------------------------------------------------------------ yield
let yieldTimer = null;
function fetchYield() {
    clearTimeout(yieldTimer);
    yieldTimer = setTimeout(async () => {
        const q = new URLSearchParams({ lat: $('dLat').value, lng: $('dLng').value, tilt: S.p.tilt, azimuth: S.p.az });
        try {
            const r = await fetch(INIT.urls.yield + '?' + q, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            S.yield = await r.json();
        } catch (e) { S.yield = null; }
        results();
    }, 400);
}

// ------------------------------------------------------------------ results
function computeEnergy() {
    const n = activePanels().length, kw = (n * (+S.p.watt || 0)) / 1000;
    if (!S.yield) return { n, kw, kwh: null };
    const simOk = S.sim && !S.sim.stale;
    const monthly = S.yield.monthly.map((m, i) => kw * m * (1 - (simOk ? S.sim.monthlyLoss[i] : 0) / 100));
    const kwh = monthly.reduce((a, b) => a + b, 0);
    const ideal = kw * S.yield.monthly.reduce((a, b) => a + b, 0);
    return { n, kw, kwh, monthly, loss: simOk && ideal > 0 ? (1 - kwh / ideal) * 100 : null };
}
function results() {
    const e = computeEnergy();
    $('resPanels').textContent = e.n;
    $('resKw').textContent = e.kw.toFixed(2) + ' kW';
    $('resLoss').textContent = e.loss == null ? '—' : e.loss.toFixed(1) + '%';
    $('resKwh').textContent = e.kwh == null ? '—' : Math.round(e.kwh).toLocaleString('en-IN') + ' kWh';
    const y = S.yield;
    $('resYield').innerHTML = y ? `${Math.round(y.kwh_per_kwp).toLocaleString('en-IN')} kWh per kW per year · ${y.source === 'pvgis' ? 'PVGIS solar data for this location' : 'estimate from Settings (set the location for real solar data)'}` : 'Loading solar data…';
    $('simNote').textContent = S.sim && !S.sim.stale ? 'Shading analysis is up to date.' : S.sim ? 'The layout changed: run the shading analysis again (Save also runs it).' : 'Year-round sun path: shadows from tanks, rooms, trees, the parapet and other rows.';
    // monthly chart
    const box = $('resMonthly');
    if (!e.monthly) { box.innerHTML = ''; return; }
    const max = Math.max(...e.monthly, 1), W = 240, H = 90, bw = W / 12;
    box.innerHTML = `<svg class="rd-month-chart" viewBox="0 0 ${W} ${H + 14}" width="100%">${e.monthly.map((m, i) => {
        const h = (m / max) * H;
        return `<rect x="${i * bw + 2}" y="${H - h}" width="${bw - 4}" height="${h}" rx="2"><title>${MONTHS[i]}: ${Math.round(m)} kWh</title></rect><text x="${i * bw + bw / 2}" y="${H + 11}" text-anchor="middle">${MONTHS[i][0]}</text>`;
    }).join('')}</svg>`;
}

// ------------------------------------------------------------------ 3D
let R = null; // { renderer, scene, camera, controls, sun, model, casters, panelMeshes, target }
function init3d() {
    if (R) return R;
    const box = $('view3d');
    const renderer = new THREE.WebGLRenderer({ antialias: true, preserveDrawingBuffer: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.shadowMap.enabled = true; renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    box.appendChild(renderer.domElement);
    const scene = new THREE.Scene();
    scene.background = new THREE.Color(0xdbeafe);
    const camera = new THREE.PerspectiveCamera(45, 1, 0.1, 2000);
    const controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = false; controls.maxPolarAngle = Math.PI * 0.49;
    scene.add(new THREE.HemisphereLight(0xffffff, 0x8a9a7b, 1.1));
    const sun = new THREE.DirectionalLight(0xfff4e0, 2.4);
    sun.castShadow = true; sun.shadow.mapSize.set(2048, 2048); sun.shadow.bias = -0.0004; sun.shadow.normalBias = 0.02;
    scene.add(sun); scene.add(sun.target);
    const ground = new THREE.Mesh(new THREE.PlaneGeometry(600, 600), new THREE.MeshStandardMaterial({ color: 0xb7c4a1, roughness: 1 }));
    ground.rotation.x = -Math.PI / 2; ground.receiveShadow = true; scene.add(ground);
    const model = new THREE.Group(); scene.add(model);
    R = { renderer, scene, camera, controls, sun, model, casters: [], panelMeshes: new Map(), target: new THREE.Vector3(), box };
    controls.addEventListener('change', render3d);
    new ResizeObserver(resize3d).observe(box);
    return R;
}
function resize3d() {
    if (!R) return;
    const w = R.box.clientWidth, h = R.box.clientHeight;
    if (!w || !h) return;
    R.renderer.setSize(w, h, false); R.camera.aspect = w / h; R.camera.updateProjectionMatrix(); render3d();
}
function render3d() { if (R) R.renderer.render(R.scene, R.camera); }
const MAT = {
    wall: new THREE.MeshStandardMaterial({ color: 0xe7e2d6, roughness: 0.95 }),
    top: new THREE.MeshStandardMaterial({ color: 0xd9d3c4, roughness: 0.95 }),
    parapet: new THREE.MeshStandardMaterial({ color: 0xdcd5c7, roughness: 0.9 }),
    room: new THREE.MeshStandardMaterial({ color: 0xe9e4d8, roughness: 0.9 }),
    tank: new THREE.MeshStandardMaterial({ color: 0x334155, roughness: 0.6 }),
    trunk: new THREE.MeshStandardMaterial({ color: 0x7c5a3a, roughness: 1 }),
    leaf: new THREE.MeshStandardMaterial({ color: 0x3f7d3a, roughness: 1 }),
    panel: new THREE.MeshStandardMaterial({ color: 0x1e3a8a, roughness: 0.35, metalness: 0.25 }),
    frame: new THREE.MeshStandardMaterial({ color: 0x94a3b8, roughness: 0.5, metalness: 0.5 }),
};
const v3 = (x, y, h) => new THREE.Vector3(x, h, -y);
function build3d() {
    init3d();
    const { model } = R;
    model.traverse((o) => { if (o.geometry) o.geometry.dispose(); if (o.material && o.material.userData && o.material.userData.own) o.material.dispose(); });
    model.clear(); R.casters = []; R.panelMeshes.clear();
    const H = +S.roof.h || 6, pts = S.roof.pts;
    if (pts.length < 3) { render3d(); return; }
    const ccw = polyArea(pts) > 0 ? pts : pts.slice().reverse();
    // building
    const shape = new THREE.Shape(ccw.map(([x, y]) => new THREE.Vector2(x, y)));
    const bg = new THREE.ExtrudeGeometry(shape, { depth: H, bevelEnabled: false });
    bg.rotateX(-Math.PI / 2);
    const building = new THREE.Mesh(bg, [MAT.top, MAT.wall]);
    building.castShadow = true; building.receiveShadow = true; model.add(building);
    // top-view photo on the ground and on the roof (Google imagery is shown live only, never saved in the picture)
    const tex = bgTexture();
    if (tex) {
        const b = S.bg, Wm = b.w * b.mpp, Hm = b.h * b.mpp;
        const gmesh = new THREE.Mesh(new THREE.PlaneGeometry(Wm, Hm), new THREE.MeshStandardMaterial({ map: tex, roughness: 1 }));
        gmesh.rotation.set(-Math.PI / 2, 0, 0); gmesh.rotateOnWorldAxis(new THREE.Vector3(0, 1, 0), (b.rot || 0) * D2R);
        gmesh.position.copy(v3(b.cx, b.cy, 0.03)); gmesh.receiveShadow = true; model.add(gmesh);
        const rg = new THREE.ShapeGeometry(new THREE.Shape(ccw.map(([x, y]) => new THREE.Vector2(x, y))));
        const pos = rg.attributes.position, uv = rg.attributes.uv, r = -(b.rot || 0) * D2R;
        for (let i = 0; i < pos.count; i++) {
            const dx = pos.getX(i) - b.cx, dy = pos.getY(i) - b.cy;
            const lx = dx * Math.cos(r) - dy * Math.sin(r), ly = dx * Math.sin(r) + dy * Math.cos(r);
            uv.setXY(i, lx / Wm + 0.5, ly / Hm + 0.5);
        }
        rg.rotateX(-Math.PI / 2);
        const rmesh = new THREE.Mesh(rg, new THREE.MeshStandardMaterial({ map: tex, roughness: 1 }));
        rmesh.position.y = H + 0.012; rmesh.receiveShadow = true; model.add(rmesh);
    }
    // parapet walls (outside the roof edge)
    const par = +S.roof.parapet || 0, thk = 0.23;
    if (par > 0.01) {
        ccw.forEach((q, i) => {
            const n = ccw[(i + 1) % ccw.length], dx = n[0] - q[0], dy = n[1] - q[1], L = Math.hypot(dx, dy);
            if (L < 0.05) return;
            const out = [dy / L, -dx / L];
            const m = new THREE.Mesh(new THREE.BoxGeometry(L + thk, par, thk), MAT.parapet);
            m.position.copy(v3((q[0] + n[0]) / 2 + (out[0] * thk) / 2, (q[1] + n[1]) / 2 + (out[1] * thk) / 2, H + par / 2));
            m.rotation.y = Math.atan2(dy, dx);
            m.castShadow = true; m.receiveShadow = true; model.add(m); R.casters.push(m);
        });
    }
    // obstacles
    S.obs.forEach((o) => {
        let m;
        if (o.type === 'room') {
            m = new THREE.Mesh(new THREE.BoxGeometry(o.w, o.h, o.d), MAT.room);
            m.position.copy(v3(o.x, o.y, H + o.h / 2)); m.rotation.y = (o.rot || 0) * D2R;
        } else if (o.type === 'tank') {
            m = new THREE.Mesh(new THREE.CylinderGeometry(o.w / 2, o.w / 2, o.h, 24), MAT.tank);
            m.position.copy(v3(o.x, o.y, H + o.h / 2));
        } else {
            const g = new THREE.Group();
            const trunkH = o.h * 0.45;
            const trunk = new THREE.Mesh(new THREE.CylinderGeometry(0.18, 0.25, trunkH, 10), MAT.trunk);
            trunk.position.y = trunkH / 2; trunk.castShadow = true; g.add(trunk); R.casters.push(trunk);
            const crown = new THREE.Mesh(new THREE.SphereGeometry(o.w / 2, 18, 14), MAT.leaf);
            crown.scale.y = Math.max(0.6, (o.h - trunkH) / o.w); crown.position.y = trunkH + ((o.h - trunkH) / 2);
            crown.castShadow = true; g.add(crown); R.casters.push(crown);
            g.position.copy(v3(o.x, o.y, 0)); model.add(g);
            return;
        }
        m.castShadow = true; m.receiveShadow = true; model.add(m); R.casters.push(m);
    });
    // panels on the structure
    const { a, s, t, rise } = panelDims();
    const front = +S.p.front || 0, A = S.p.az * D2R, { b } = frame();
    const heat = !R.plain && $('heatOn').checked && S.sim && !S.sim.stale;
    const geo = new THREE.BoxGeometry(a, 0.04, s), postGeo = new THREE.BoxGeometry(0.05, 1, 0.05);
    activePanels().forEach((p) => {
        let mat = MAT.panel;
        if (heat && S.sim.perPanel[p.key] != null) { mat = MAT.panel.clone(); mat.color = new THREE.Color(heatColour(S.sim.perPanel[p.key])); mat.userData.own = true; }
        const m = new THREE.Mesh(geo, mat);
        m.position.copy(v3(p.c[0], p.c[1], H + front + rise / 2 + 0.02));
        m.rotation.set(t, Math.PI - A, 0, 'YXZ');
        m.castShadow = true; m.receiveShadow = true; m.userData.key = p.key;
        model.add(m); R.casters.push(m); R.panelMeshes.set(p.key, m);
        // two posts: low edge and high edge
        [[-1, front], [1, front + rise]].forEach(([side, hgt]) => {
            if (hgt < 0.05) return;
            const off = (side * (s * Math.cos(t))) / 2 * 0.85;
            const post = new THREE.Mesh(postGeo, MAT.frame);
            post.scale.y = hgt; post.position.copy(v3(p.c[0] + b[0] * off, p.c[1] + b[1] * off, H + hgt / 2));
            model.add(post);
        });
    });
    // camera target / framing
    const c = centroid(pts), ext = Math.max(...pts.map((q) => Math.hypot(q[0] - c[0], q[1] - c[1]))) + 4;
    R.target.copy(v3(c[0], c[1], H));
    if (!R.framed) { R.camera.position.copy(R.target).add(new THREE.Vector3(ext * 1.1, ext * 1.25, ext * 1.6)); R.controls.target.copy(R.target); R.controls.update(); R.framed = true; }
    R.ext = ext;
    const sc = R.sun.shadow.camera; sc.left = -ext * 1.8; sc.right = ext * 1.8; sc.top = ext * 1.8; sc.bottom = -ext * 1.8; sc.near = 1; sc.far = 400; sc.updateProjectionMatrix();
    updateSun();
}
function updateSun() {
    if (!R) return;
    const sp = sunPos(+S.sun.month, +S.sun.hour);
    const dir = sunVec3(Math.max(sp.alt, 0.5), sp.az);
    R.sun.position.copy(R.target).addScaledVector(dir, 150);
    R.sun.target.position.copy(R.target);
    R.sun.intensity = sp.alt > 0 ? 2.4 : 0.05;
    render3d();
}

// view switching
function switchView(v) {
    document.querySelectorAll('[data-view]').forEach((b) => b.classList.toggle('active', b.dataset.view === v));
    $('plan').hidden = v !== 'plan'; $('view3d').hidden = v !== '3d';
    if (v === '3d') { init3d(); build3d(); resize3d(); }
}
document.querySelectorAll('[data-view]').forEach((b) => b.addEventListener('click', () => switchView(b.dataset.view)));

// ------------------------------------------------------------------ shading simulation
async function simulate() {
    init3d(); build3d();
    R.model.updateMatrixWorld(true);
    const list = activePanels();
    $('simProgWrap').hidden = false; $('btnSimulate').disabled = true;
    const ray = new THREE.Raycaster(); ray.far = 200;
    const { a, s } = panelDims();
    const samples = [];
    for (const fu of [-1 / 3, 0, 1 / 3]) for (const fs of [-0.3, 0.3]) samples.push(new THREE.Vector3(fu * a, 0.03, fs * s));
    const per = new Map(list.map((p) => [p.key, { sh: 0, tot: 0 }]));
    const monthSh = new Array(12).fill(0), monthTot = new Array(12).fill(0);
    const pts = new Map(), normals = new Map();
    list.forEach((p) => {
        const m = R.panelMeshes.get(p.key); if (!m) return;
        pts.set(p.key, samples.map((v) => v.clone().applyMatrix4(m.matrixWorld)));
        normals.set(p.key, new THREE.Vector3(0, 1, 0).applyQuaternion(m.getWorldQuaternion(new THREE.Quaternion())));
    });
    for (let mo = 0; mo < 12; mo++) {
        for (let h = 6; h <= 18; h += 0.5) {
            const sp = sunPos(mo, h);
            if (sp.alt < 2) continue;
            const dir = sunVec3(sp.alt, sp.az), strength = Math.pow(Math.sin(sp.alt * D2R), 0.5);
            list.forEach((p) => {
                const m = R.panelMeshes.get(p.key), P = pts.get(p.key); if (!m) return;
                const w = Math.max(0, normals.get(p.key).dot(dir)) * strength;
                if (w <= 0) return;
                let blocked = 0;
                for (const o of P) {
                    ray.set(o, dir);
                    const hits = ray.intersectObjects(R.casters, false);
                    if (hits.some((x) => x.object !== m && x.distance > 0.02)) blocked++;
                }
                const frac = blocked / P.length, rec = per.get(p.key);
                rec.sh += w * frac; rec.tot += w; monthSh[mo] += w * frac; monthTot[mo] += w;
            });
        }
        $('simProg').style.width = ((mo + 1) / 12) * 100 + '%';
        await new Promise((r) => setTimeout(r, 0));
    }
    const perPanel = {};
    per.forEach((v, k) => { perPanel[k] = v.tot ? +(DIRECT_SHARE * (v.sh / v.tot) * 100).toFixed(2) : 0; });
    S.sim = { perPanel, monthlyLoss: monthTot.map((t, i) => (t ? +(DIRECT_SHARE * (monthSh[i] / t) * 100).toFixed(2) : 0)), stale: false };
    $('simProgWrap').hidden = true; $('btnSimulate').disabled = false;
    results(); renderPlan(); if ($('heatOn').checked) build3d();
}
$('btnSimulate').addEventListener('click', () => { if (!activePanels().length) return alert('Place some panels first.'); simulate(); });

// ------------------------------------------------------------------ snapshot, save, quotation
function snapshot() {
    init3d(); R.plain = true; build3d(); R.plain = false;
    const { renderer, camera } = R;
    const prevSize = renderer.getSize(new THREE.Vector2()), prevPos = camera.position.clone(), prevAspect = camera.aspect, prevRatio = renderer.getPixelRatio();
    renderer.setPixelRatio(1); renderer.setSize(1200, 760, false);
    camera.aspect = 1200 / 760; camera.updateProjectionMatrix();
    camera.position.copy(R.target).add(new THREE.Vector3(R.ext * 1.0, R.ext * 1.15, R.ext * 1.5)); camera.lookAt(R.target);
    const keep = { month: S.sun.month, hour: S.sun.hour };
    S.sun.month = 2; S.sun.hour = 10.5; updateSun();
    renderer.render(R.scene, camera);
    const url = renderer.domElement.toDataURL('image/png');
    S.sun.month = keep.month; S.sun.hour = keep.hour;
    camera.position.copy(prevPos); camera.lookAt(R.controls.target); renderer.setPixelRatio(prevRatio);
    renderer.setSize(prevSize.x || 1, prevSize.y || 1, false); camera.aspect = prevAspect; camera.updateProjectionMatrix();
    R.controls.update(); build3d();
    return url;
}
async function save() {
    const name = $('dName').value.trim();
    if (!name) { $('dName').focus(); msg('Give the design a name.', true); return false; }
    $('btnSave') && ($('btnSave').disabled = true);
    try {
        if (activePanels().length && (!S.sim || S.sim.stale)) { msg('Running shading analysis…'); await simulate(); }
        if (!S.yield) { await new Promise((r) => { fetchYield(); setTimeout(r, 1500); }); }
        const e = computeEnergy();
        const body = {
            name, lead_id: $('dLead').value || null,
            latitude: $('dLat').value || null, longitude: $('dLng').value || null,
            data: { version: 1, roof: S.roof, obs: S.obs, p: S.p, removed: [...S.removed], sun: S.sun, sim: S.sim ? { perPanel: S.sim.perPanel, monthlyLoss: S.sim.monthlyLoss } : null,
                bg: S.bg ? Object.fromEntries(Object.entries(S.bg).filter(([k]) => k !== 'url')) : null, google: S.google },
            photo_ids: S.photos.map((p) => p.id),
            panel_item_id: S.p.itemId, panel_watt: S.p.watt, panel_count: e.n,
            kwh_per_kwp: S.yield ? S.yield.kwh_per_kwp : 0, shading_loss: e.loss || 0,
            monthly_kwh: e.monthly ? e.monthly.map((m) => +m.toFixed(1)) : [], yield_source: S.yield ? S.yield.source : null,
            snapshot: snapshot(),
        };
        msg('Saving…');
        const r = await fetch(INIT.urls.save, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': INIT.csrf, 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(body) });
        const res = await r.json().catch(() => ({ ok: false, error: 'Unexpected reply from the server (HTTP ' + r.status + ').' }));
        if (!res.ok) { msg(res.error || 'Could not save.', true); return false; }
        S.dirty = false;
        if (!INIT.id) { location.href = res.url; return true; }
        msg(`Saved ${res.design_no} · ${res.capacity_kw} kW · ${Math.round(res.kwh_year).toLocaleString('en-IN')} kWh/year · ${new Date().toLocaleTimeString()}`);
        return true;
    } catch (err) { msg('Could not save: ' + err.message, true); return false; }
    finally { $('btnSave') && ($('btnSave').disabled = false); }
}
function msg(t, bad) { const m = $('saveMsg'); m.textContent = t; m.className = bad ? 'text-danger' : 'text-muted'; }
if ($('btnSave')) $('btnSave').addEventListener('click', save);
if ($('btnQuote')) $('btnQuote').addEventListener('click', async () => {
    if (!INIT.urls.quotation) return;
    if (S.dirty && INIT.canEdit && !(await save())) return;
    location.href = INIT.urls.quotation;
});
window.addEventListener('beforeunload', (e) => { if (S.dirty && INIT.canEdit) { e.preventDefault(); e.returnValue = ''; } });
['dName', 'dLead'].forEach((id) => $(id).addEventListener('change', () => { S.dirty = true; }));

// ------------------------------------------------------------------ photos
const csrfHeaders = { 'X-CSRF-TOKEN': INIT.csrf, 'X-Requested-With': 'XMLHttpRequest' };
function bgUrl() {
    if (!S.bg) return null;
    if (S.bg.source === 'google') return googleStaticUrl();
    return INIT.urls.photoBase + S.bg.photoId;
}
function bgCorners() {
    if (!S.bg || S.bg.hidden) return [];
    const b = S.bg, hw = (b.w * b.mpp) / 2, hh = (b.h * b.mpp) / 2, r = (b.rot || 0) * D2R;
    return [[-hw, -hh], [hw, -hh], [hw, hh], [-hw, hh]].map(([x, y]) => [b.cx + x * Math.cos(r) - y * Math.sin(r), b.cy + x * Math.sin(r) + y * Math.cos(r)]);
}
const texCache = new Map();
function bgTexture() {
    if (!S.bg || S.bg.hidden || !R) return null;
    if (S.bg.source === 'google' && R.plain) return null;
    const url = bgUrl(); if (!url) return null;
    if (texCache.has(url)) return texCache.get(url) || null;
    texCache.set(url, null);
    const loader = new THREE.TextureLoader(); loader.setCrossOrigin('anonymous');
    loader.load(url, (t) => { t.colorSpace = THREE.SRGBColorSpace; texCache.set(url, t); build3d(); }, undefined, () => texCache.set(url, false));
    return null;
}
async function upload(file, kind) {
    const fd = new FormData(); fd.append('photo', file); fd.append('kind', kind); if (INIT.id) fd.append('design_id', INIT.id);
    const r = await fetch(INIT.urls.photoUpload, { method: 'POST', headers: csrfHeaders, body: fd });
    const res = await r.json().catch(() => ({ ok: false, error: 'Upload failed (HTTP ' + r.status + ').' }));
    if (!res.ok) throw new Error(res.error || 'Upload failed.');
    return res;
}
function maybeUseGps(ph) {
    if (ph.lat == null || $('dLat').value) return;
    if (confirm(`This photo has a GPS position (${ph.lat}, ${ph.lng}). Use it as the site location?`)) {
        $('dLat').value = ph.lat; $('dLng').value = ph.lng; fetchYield(); changed({ geometry: true });
    }
}
function showBgCtl() {
    const b = S.bg;
    $('bgCtl').hidden = !b;
    if (!b) return;
    $('bgUploadCtl').hidden = b.source === 'google';
    $('bgOpacity').value = b.opacity ?? 0.75; $('bgRot').value = b.rot || 0; $('bgRotVal').textContent = (b.rot || 0) + '°';
    $('bgHide').innerHTML = b.hidden ? '<i class="bi bi-eye"></i> Show' : '<i class="bi bi-eye-slash"></i> Hide';
    $('bgInfo').textContent = b.source === 'google'
        ? `Google satellite · ${Math.round(b.w * b.mpp)} × ${Math.round(b.h * b.mpp)} m · shown live, not saved`
        : `Photo covers ${(b.w * b.mpp).toFixed(1)} × ${(b.h * b.mpp).toFixed(1)} m${b.calibrated ? '' : ' · set the scale!'}`;
}
$('bgFile').addEventListener('change', async () => {
    const f = $('bgFile').files[0]; $('bgFile').value = ''; if (!f) return;
    try {
        hint('Uploading photo…');
        const ph = await upload(f, 'plan');
        S.photos.push(ph);
        const c = centroid(S.roof.pts), ext = Math.max(...S.roof.pts.map((q) => Math.hypot(q[0] - c[0], q[1] - c[1]))) * 2 + 8;
        S.bg = { source: 'upload', photoId: ph.id, w: ph.w, h: ph.h, mpp: ext / Math.max(ph.w, ph.h), rot: 0, cx: c[0], cy: c[1], opacity: 0.75, calibrated: false };
        view = null; S.dirty = true; showBgCtl(); renderPlan(); build3d(); renderPhotos();
        hint('Now click "Set scale": click two points on the photo you know the distance between.');
        maybeUseGps(ph);
    } catch (e) { hint(''); alert(e.message); }
});
$('bgOpacity').addEventListener('input', () => { if (!S.bg) return; S.bg.opacity = +$('bgOpacity').value; S.dirty = true; renderPlan(); });
$('bgRot').addEventListener('input', () => { if (!S.bg) return; S.bg.rot = +$('bgRot').value; $('bgRotVal').textContent = S.bg.rot + '°'; S.dirty = true; renderPlan(); clearTimeout(rebuildTimer); rebuildTimer = setTimeout(build3d, 200); });
$('bgScale').addEventListener('click', () => { switchView('plan'); S.mode = 'scale'; S.scalePts = []; hint('Click two points on the photo whose real distance you know (e.g. the two ends of a wall). Esc cancels.'); renderPlan(); });
function scaleClick(p) {
    S.scalePts.push(p); renderPlan();
    if (S.scalePts.length < 2) return;
    const [a, b] = S.scalePts, d = Math.hypot(b[0] - a[0], b[1] - a[1]);
    setTimeout(() => {
        const real = parseFloat(prompt('Real distance between the two points, in metres', ''));
        if (Number.isFinite(real) && real > 0.2 && d > 0.05 && S.bg) {
            const k = real / d;
            S.bg.mpp *= k; S.bg.cx = a[0] + (S.bg.cx - a[0]) * k; S.bg.cy = a[1] + (S.bg.cy - a[1]) * k; S.bg.calibrated = true;
            view = null; S.dirty = true; showBgCtl(); build3d();
            hint('Scale set. Turn the photo so north is up, then trace the roof (Draw outline) over it.');
        } else hint('');
        S.mode = 'select'; S.scalePts = []; renderPlan();
    }, 30);
}
$('bgMove').addEventListener('click', () => {
    const on = S.mode !== 'movebg'; S.mode = on ? 'movebg' : 'select';
    $('plan').classList.toggle('move-bg', on); $('bgMove').classList.toggle('active', on);
    hint(on ? 'Drag the photo to line it up with the roof outline. Click "Move photo" again when done.' : '');
});
$('bgHide').addEventListener('click', () => { if (!S.bg) return; S.bg.hidden = !S.bg.hidden; S.dirty = true; showBgCtl(); renderPlan(); build3d(); });
$('bgRemove').addEventListener('click', () => { if (!S.bg || !confirm('Remove the background photo from this design?')) return; S.bg = null; view = null; S.dirty = true; showBgCtl(); renderPlan(); build3d(); });

// site photos
function renderPhotos() {
    const g = $('siteGrid'); g.innerHTML = '';
    S.photos.filter((p) => p.kind === 'site').forEach((ph) => {
        const d = document.createElement('div'); d.className = 'ph';
        d.innerHTML = `<img src="${ph.url}" alt="" loading="lazy" title="${(ph.caption || '').replace(/"/g, '&quot;')}">` + (INIT.canEdit ? '<button type="button" title="Delete">×</button>' : '');
        d.querySelector('img').addEventListener('click', () => window.open(ph.url, '_blank'));
        const del = d.querySelector('button');
        if (del) del.addEventListener('click', async () => {
            if (!confirm('Delete this photo?')) return;
            const r = await fetch(INIT.urls.photoBase + ph.id + '/delete', { method: 'POST', headers: csrfHeaders });
            const res = await r.json().catch(() => ({}));
            if (res.ok) { S.photos = S.photos.filter((x) => x !== ph); renderPhotos(); } else alert(res.error || 'Could not delete.');
        });
        g.appendChild(d);
    });
    if (!g.children.length) g.innerHTML = '<div class="small text-muted" style="grid-column:1/-1">Roof, tank, staircase, EB meter, the view south… anything useful for the team.</div>';
}
$('siteFiles').addEventListener('change', async () => {
    const files = [...$('siteFiles').files]; $('siteFiles').value = '';
    $('upProgWrap').hidden = false;
    for (let i = 0; i < files.length; i++) {
        try { const ph = await upload(files[i], 'site'); S.photos.push(ph); S.dirty = true; renderPhotos(); maybeUseGps(ph); } catch (e) { alert(files[i].name + ': ' + e.message); }
        $('upProg').style.width = ((i + 1) / files.length) * 100 + '%';
    }
    $('upProgWrap').hidden = true; $('upProg').style.width = '0%';
});

// ------------------------------------------------------------------ Google (needs a key in Settings)
const KEY = INIT.mapsKey;
function needKey() {
    alert('Google maps features need a Google Maps API key.\n\nAdministration → Settings → "Google Maps (3D roof design)", or google.mapsKey in .env.\nSee the User Manual (3D roof design) for how to get the key.');
    return false;
}
function siteLatLng() { const la = parseFloat($('dLat').value), ln = parseFloat($('dLng').value); return Number.isFinite(la) && Number.isFinite(ln) ? [la, ln] : null; }
/** metres per pixel of a Google static map at this zoom (scale=2 image). */
const mppAt = (latDeg, zoom) => (156543.03392 * Math.cos(latDeg * D2R)) / 2 ** zoom / 2;
function googleStaticUrl() {
    const ll = siteLatLng(); if (!KEY || !ll || !S.bg) return null;
    return `https://maps.googleapis.com/maps/api/staticmap?center=${ll[0]},${ll[1]}&zoom=${S.bg.zoom || 20}&size=640x640&scale=2&maptype=satellite&key=${encodeURIComponent(KEY)}`;
}
$('bgGoogle').addEventListener('click', () => {
    if (!KEY) return needKey();
    const ll = siteLatLng(); if (!ll) { alert('Set the site location first (Find on map, or type latitude / longitude).'); return; }
    const c = centroid(S.roof.pts), ext = Math.max(...S.roof.pts.map((q) => Math.hypot(q[0] - c[0], q[1] - c[1])));
    const zoom = ext > 35 ? 19 : 20;
    // The plan's (0,0) is the site location, so the map is centred on 0,0 and north-up; scale comes from the zoom.
    S.bg = { source: 'google', zoom, w: 1280, h: 1280, mpp: mppAt(ll[0], zoom), rot: 0, cx: 0, cy: 0, opacity: 0.9, calibrated: true };
    view = null; S.dirty = true; showBgCtl(); renderPlan(); build3d();
    hint('Satellite photo of the site. Drag the roof corners onto the real roof, or click "Detect roof".');
});

let mapsReady = null;
function loadMaps() {
    if (!KEY) return Promise.reject(new Error('no key'));
    if (mapsReady) return mapsReady;
    mapsReady = new Promise((resolve, reject) => {
        window.__rdMapsReady = () => resolve(window.google.maps);
        const s = document.createElement('script');
        s.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(KEY)}&libraries=places,geocoding&v=weekly&loading=async&callback=__rdMapsReady`;
        s.async = true; s.onerror = () => reject(new Error('Google Maps could not load (check the key and its allowed websites).'));
        document.head.appendChild(s);
    });
    return mapsReady;
}
let gmap = null;
$('btnMap').addEventListener('click', async () => {
    if (!KEY) return needKey();
    const modal = window.bootstrap.Modal.getOrCreateInstance($('mapModal'));
    modal.show();
    try {
        const maps = await loadMaps();
        const ll = siteLatLng();
        if (!gmap) {
            gmap = new maps.Map($('mapCanvas'), { center: ll ? { lat: ll[0], lng: ll[1] } : { lat: 11.0, lng: 78.0 }, zoom: ll ? 20 : 6, mapTypeId: 'satellite', tilt: 0, rotateControl: false, streetViewControl: false, fullscreenControl: false });
            await initSearch(maps);
        } else if (ll) { gmap.setCenter({ lat: ll[0], lng: ll[1] }); }
    } catch (e) { $('mapMsg').textContent = e.message; }
});
async function initSearch(maps) {
    const box = $('mapSearchBox');
    const goTo = (loc, vp) => { if (vp) gmap.fitBounds(vp); else { gmap.setCenter(loc); gmap.setZoom(20); } };
    try {
        const { PlaceAutocompleteElement } = await maps.importLibrary('places');
        const pac = new PlaceAutocompleteElement({ includedRegionCodes: ['in'] });
        box.appendChild(pac);
        const onPick = async (place) => { await place.fetchFields({ fields: ['location', 'viewport', 'formattedAddress'] }); goTo(place.location, place.viewport); gmap.setZoom(Math.max(gmap.getZoom(), 19)); $('mapMsg').textContent = place.formattedAddress + ' — drag so the cross is on the roof.'; };
        pac.addEventListener('gmp-select', (ev) => onPick(ev.placePrediction.toPlace()));
        pac.addEventListener('gmp-placeselect', (ev) => onPick(ev.place));
    } catch (e) {
        // fallback: plain box + Geocoding API
        const inp = document.createElement('input'); inp.className = 'form-control form-control-sm'; inp.placeholder = 'Address, area or landmark – press Enter'; box.appendChild(inp);
        inp.addEventListener('keydown', async (ev) => {
            if (ev.key !== 'Enter') return; ev.preventDefault();
            const { Geocoder } = await maps.importLibrary('geocoding');
            new Geocoder().geocode({ address: inp.value, region: 'in' }, (res, st) => { if (st === 'OK' && res[0]) goTo(res[0].geometry.location, res[0].geometry.viewport); else $('mapMsg').textContent = 'Not found: ' + st; });
        });
    }
}
$('mapUse').addEventListener('click', () => {
    if (!gmap) return;
    const c = gmap.getCenter();
    $('dLat').value = c.lat().toFixed(6); $('dLng').value = c.lng().toFixed(6);
    window.bootstrap.Modal.getOrCreateInstance($('mapModal')).hide();
    fetchYield();
    if (!S.bg || S.bg.source === 'google') $('bgGoogle').click(); else changed({ geometry: true });
});

/** Google Solar API: roof outline (approx.), pitch and facing from the building under the site location. */
$('btnDetect').addEventListener('click', async () => {
    if (!KEY) return needKey();
    const ll = siteLatLng(); if (!ll) { alert('Set the site location on the roof first (Find on map).'); return; }
    $('btnDetect').disabled = true; hint('Asking Google Solar about this building…');
    try {
        const u = `https://solar.googleapis.com/v1/buildingInsights:findClosest?location.latitude=${ll[0]}&location.longitude=${ll[1]}&requiredQuality=BASE&key=${encodeURIComponent(KEY)}`;
        const r = await fetch(u); const j = await r.json();
        if (!r.ok) throw new Error(j.error && j.error.message ? j.error.message : 'HTTP ' + r.status);
        applyBuildingInsights(j);
    } catch (e) { hint(''); alert('Google Solar could not detect this roof: ' + e.message + '\n\nYou can still trace it over the satellite photo.'); }
    finally { $('btnDetect').disabled = false; }
});
function applyBuildingInsights(j) {
    const ll = siteLatLng() || [j.center.latitude, j.center.longitude];
    const toXY = (p) => [(p.longitude - ll[1]) * 111320 * Math.cos(ll[0] * D2R), (p.latitude - ll[0]) * 110540];
    const sp = j.solarPotential || {};
    const segs = (sp.roofSegmentStats || []).filter((s) => s.boundingBox);
    const box = (bb) => { const a = toXY(bb.sw), b = toXY(bb.ne); return [[a[0], a[1]], [b[0], a[1]], [b[0], b[1]], [a[0], b[1]]]; };
    let pts = segs.length ? hull(segs.flatMap((s) => box(s.boundingBox))) : (j.boundingBox ? box(j.boundingBox) : null);
    if (!pts || pts.length < 3) throw new Error('no roof outline in the answer');
    pts = pts.map(([x, y]) => [+x.toFixed(2), +y.toFixed(2)]);
    if (polyArea(pts) < 0) pts.reverse();
    S.roof.pts = pts;
    const main = segs.slice().sort((a, b) => (b.stats?.areaMeters2 || 0) - (a.stats?.areaMeters2 || 0))[0];
    const pitch = main ? main.pitchDegrees || 0 : 0;
    if (main && pitch > 8) {
        S.p.tilt = Math.round(pitch); S.p.az = Math.round((main.azimuthDegrees || 180) / 5) * 5; S.p.front = 0.15; S.p.gapAuto = false; S.p.gap = 0.02;
    }
    if (sp.panelHeightMeters && sp.panelWidthMeters && !S.p.itemId) { S.p.len = sp.panelHeightMeters; S.p.wid = sp.panelWidthMeters; }
    const cfg = (sp.solarPanelConfigs || []).slice(-1)[0];
    S.google = {
        date: j.imageryDate ? `${j.imageryDate.day}-${j.imageryDate.month}-${j.imageryDate.year}` : null, quality: j.imageryQuality || null,
        roofArea: sp.wholeRoofStats ? Math.round(sp.wholeRoofStats.areaMeters2) : null, maxPanels: sp.maxArrayPanelsCount || null,
        panelW: sp.panelCapacityWatts || null, maxKwh: cfg ? Math.round(cfg.yearlyEnergyDcKwh) : null, pitch: Math.round(pitch), segments: segs.length,
    };
    showGoogle();
    if (!S.bg && KEY) $('bgGoogle').click();
    S.removed.clear(); view = null; loadForm(); changed({ geometry: true });
    hint(pitch > 8 ? `Sloped roof (${Math.round(pitch)}°): panels set flush to the slope. Check the outline over the photo.` : 'Roof detected. Drag the corners to match the photo exactly, then add the tank and staircase room.');
}
function showGoogle() {
    const g = S.google, box = $('googleInfo');
    if (!g) { box.hidden = true; return; }
    box.hidden = false;
    box.innerHTML = `<div class="alert alert-success py-1 px-2 mb-0"><b>Google Solar</b>: roof ${g.roofArea ?? '?'} m² in ${g.segments} part(s), ${g.pitch > 8 ? `sloped ${g.pitch}°` : 'flat roof'}.
        ${g.maxPanels ? `Google's own estimate: up to ${g.maxPanels} panels of ${g.panelW} W${g.maxKwh ? ` ≈ ${g.maxKwh.toLocaleString('en-IN')} kWh/yr` : ''}.` : ''}
        <span class="text-muted">Imagery ${g.date || ''} (${(g.quality || '').toLowerCase()}).</span></div>`;
}
if (!KEY) { ['btnMap', 'bgGoogle', 'btnDetect'].forEach((id) => { $(id).title = 'Needs a Google Maps key in Settings'; $(id).classList.add('opacity-50'); }); }
if (!INIT.canEdit) ['bgGoogle', 'btnDetect', 'bgScale', 'bgMove', 'bgRemove'].forEach((id) => { $(id).disabled = true; });

// ------------------------------------------------------------------ start
if (!INIT.canEdit) {
    document.querySelectorAll('.rd-side input, .rd-side select, .rd-side button, #dName, #dLead, #dLat, #dLng, #btnLocate').forEach((x) => { x.disabled = true; });
}
loadForm(true);
layout();
renderPlan();
renderObsList();
sunLabel();
fetchYield();
results();
showBgCtl();
showGoogle();
renderPhotos();
window.__rd = { S, layout, simulate, snapshot, computeEnergy, applyBuildingInsights, renderPlan, mppAt }; // for testing
