<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?><?= $design['id'] ? '3D Design ' . esc($design['design_no']) : 'New 3D roof design' ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php
$init = [
    'id'        => $design['id'],
    'name'      => $design['name'] ?? '',
    'lead_id'   => $design['lead_id'] ?? null,
    'latitude'  => $design['latitude'] !== null ? (float) $design['latitude'] : null,
    'longitude' => $design['longitude'] !== null ? (float) $design['longitude'] : null,
    'data'      => $design['data'] ? json_decode((string) $design['data'], true) : null,
    'panels'    => $panels,
    'canEdit'   => $canEdit,
    'photos'    => $photos,
    'mapsKey'   => $mapsKey,
    'urls'      => [
        'photoUpload' => site_url('admin/designs/photos'),
        'photoBase'   => site_url('admin/designs/photos/'),
        'save'      => site_url('admin/designs/save' . ($design['id'] ? '/' . $design['id'] : '')),
        'yield'     => site_url('admin/designs/yield'),
        'quotation' => $design['id'] ? site_url('admin/designs/' . $design['id'] . '/quotation') : null,
        'designs'   => site_url('admin/designs'),
    ],
    'csrf'      => csrf_hash(),
];
?>
<link rel="stylesheet" href="<?= base_url('assets/css/roof-designer.css') ?>?v=1">
<div class="rd-top card card-body py-2 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label small mb-0">Design name</label>
            <input id="dName" class="form-control form-control-sm" maxlength="150" placeholder="Customer · site" value="<?= esc((string) $design['name']) ?>"></div>
        <div class="col-md-3"><label class="form-label small mb-0">Lead</label>
            <select id="dLead" class="form-select form-select-sm" data-search><option value="">— None —</option>
                <?php foreach ($leads as $l): ?><option value="<?= $l['id'] ?>" <?= (int) $design['lead_id'] === (int) $l['id'] ? 'selected' : '' ?>><?= esc($l['name'] . ($l['city'] ? ' · ' . $l['city'] : '')) ?></option><?php endforeach ?>
            </select></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-0">Latitude</label><input id="dLat" type="number" step="0.000001" class="form-control form-control-sm" placeholder="e.g. 11.341"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-0">Longitude</label><input id="dLng" type="number" step="0.000001" class="form-control form-control-sm" placeholder="e.g. 77.717"></div>
        <div class="col-md-2 d-flex gap-1">
            <button type="button" id="btnLocate" class="btn btn-sm btn-outline-secondary" title="Use this phone's location (stand on the roof)"><i class="bi bi-geo-alt"></i></button>
            <button type="button" id="btnMap" class="btn btn-sm btn-outline-secondary" title="Find the house on the satellite map"><i class="bi bi-map"></i></button>
            <?php if ($canEdit): ?><button type="button" id="btnSave" class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-save"></i> Save</button><?php endif ?>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center mt-2 small">
        <span id="saveMsg" class="text-muted"><?= $design['id'] ? 'Design ' . esc($design['design_no']) : 'Not saved yet' ?></span>
        <?php if ($lead): ?><a href="<?= site_url('admin/leads/' . $lead['id']) ?>" class="ms-2"><i class="bi bi-person"></i> <?= esc($lead['name']) ?></a><?php endif ?>
        <?php if (! empty($design['address'])): ?><span class="text-muted ms-2"><i class="bi bi-geo"></i> <?= esc($design['address']) ?></span><?php endif ?>
        <span class="ms-auto"></span>
        <?php if ($quotation): ?><a class="btn btn-sm btn-outline-success" href="<?= site_url('admin/quotations/' . $quotation['id']) ?>"><i class="bi bi-file-earmark-text"></i> <?= esc($quotation['quote_no']) ?></a><?php endif ?>
        <?php if (can('quotations.manage') && \App\Libraries\Features::on('quotations')): ?><button type="button" id="btnQuote" class="btn btn-sm btn-success" <?= $design['id'] ? '' : 'disabled title="Save the design first"' ?>><i class="bi bi-file-earmark-plus"></i> Create quotation from design</button><?php endif ?>
        <?php if ($design['id'] && $canEdit): ?><form method="post" action="<?= site_url('admin/designs/' . $design['id'] . '/delete') ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" data-confirm="Delete this design?"><i class="bi bi-trash"></i></button></form><?php endif ?>
    </div>
</div>

<div class="row g-3">
    <!-- ===================== settings ===================== -->
    <div class="col-lg-3 rd-side">
        <div class="card mb-3"><div class="card-header bg-white fw-semibold py-2"><i class="bi bi-house"></i> Roof</div><div class="card-body py-2">
            <div class="row g-2">
                <div class="col-6"><label class="form-label small mb-0">Building height (m)</label><input id="rH" type="number" step="0.1" min="2" max="60" class="form-control form-control-sm"></div>
                <div class="col-6"><label class="form-label small mb-0">Parapet wall (m)</label><input id="rPar" type="number" step="0.05" min="0" max="3" class="form-control form-control-sm"></div>
            </div>
            <div class="small text-muted mt-2 mb-1">Quick shape (metres)</div>
            <div class="input-group input-group-sm mb-2">
                <input id="rW" type="number" step="0.1" min="2" class="form-control" placeholder="Width" value="12">
                <span class="input-group-text">×</span>
                <input id="rD" type="number" step="0.1" min="2" class="form-control" placeholder="Depth" value="10">
                <button id="shapeRect" class="btn btn-outline-secondary" type="button" title="Rectangle"><i class="bi bi-square"></i></button>
                <button id="shapeL" class="btn btn-outline-secondary" type="button" title="L-shape"><i class="bi bi-bounding-box-circles"></i></button>
            </div>
            <div class="d-flex gap-1 flex-wrap">
                <button id="btnDraw" class="btn btn-sm btn-outline-primary" type="button"><i class="bi bi-pencil"></i> Draw outline</button>
                <button id="btnRotL" class="btn btn-sm btn-outline-secondary" type="button" title="Rotate roof 5° left"><i class="bi bi-arrow-counterclockwise"></i></button>
                <button id="btnRotR" class="btn btn-sm btn-outline-secondary" type="button" title="Rotate roof 5° right"><i class="bi bi-arrow-clockwise"></i></button>
            </div>
            <div class="form-text">Drag the corners; click an edge length to type the exact metres. North is up.</div>
        </div></div>

        <div class="card mb-3"><div class="card-header bg-white fw-semibold py-2"><i class="bi bi-image"></i> Photos</div><div class="card-body py-2">
            <div class="small fw-semibold mb-1">Top-view photo <span class="text-muted fw-normal">(trace the roof over it)</span></div>
            <div class="d-flex gap-1 flex-wrap mb-2" id="bgButtons">
                <label class="btn btn-sm btn-outline-primary mb-0 <?= $canEdit ? '' : 'disabled' ?>"><i class="bi bi-upload"></i> Upload photo<input type="file" id="bgFile" accept="image/jpeg,image/png,image/webp" hidden></label>
                <button type="button" id="bgGoogle" class="btn btn-sm btn-outline-secondary"><i class="bi bi-globe-asia-australia"></i> Satellite</button>
                <button type="button" id="btnDetect" class="btn btn-sm btn-outline-success"><i class="bi bi-magic"></i> Detect roof</button>
            </div>
            <div id="bgCtl" hidden>
                <div class="small text-muted mb-1" id="bgInfo"></div>
                <label class="form-label small mb-0">See-through</label>
                <input type="range" id="bgOpacity" min="0.15" max="1" step="0.05" class="form-range">
                <div id="bgUploadCtl">
                    <label class="form-label small mb-0">Turn photo <span id="bgRotVal">0°</span></label>
                    <input type="range" id="bgRot" min="-180" max="180" step="0.5" class="form-range">
                    <div class="d-flex gap-1 flex-wrap">
                        <button type="button" id="bgScale" class="btn btn-sm btn-outline-primary"><i class="bi bi-rulers"></i> Set scale</button>
                        <button type="button" id="bgMove" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrows-move"></i> Move photo</button>
                    </div>
                </div>
                <div class="d-flex gap-1 flex-wrap mt-1">
                    <button type="button" id="bgHide" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye-slash"></i> Hide</button>
                    <button type="button" id="bgRemove" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i> Remove</button>
                </div>
            </div>
            <div id="googleInfo" class="small mt-2" hidden></div>
            <hr class="my-2">
            <div class="small fw-semibold mb-1">Site photos</div>
            <label class="btn btn-sm btn-outline-secondary mb-2 <?= $canEdit ? '' : 'disabled' ?>"><i class="bi bi-camera"></i> Add photos<input type="file" id="siteFiles" accept="image/jpeg,image/png,image/webp" multiple hidden></label>
            <div id="siteGrid" class="rd-photos"></div>
            <div class="progress mt-1" style="height:4px" hidden id="upProgWrap"><div class="progress-bar" id="upProg" style="width:0%"></div></div>
        </div></div>

        <div class="card mb-3"><div class="card-header bg-white fw-semibold py-2"><i class="bi bi-boxes"></i> On the roof</div><div class="card-body py-2">
            <div class="d-flex gap-1 flex-wrap mb-2">
                <button class="btn btn-sm btn-outline-secondary" type="button" data-add="tank"><i class="bi bi-cylinder"></i> Water tank</button>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-add="room"><i class="bi bi-box"></i> Staircase room</button>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-add="tree"><i class="bi bi-tree"></i> Tree / pole</button>
            </div>
            <div id="obsList" class="small"></div>
        </div></div>

        <div class="card mb-3"><div class="card-header bg-white fw-semibold py-2"><i class="bi bi-grid-3x3"></i> Panels</div><div class="card-body py-2">
            <label class="form-label small mb-0">Panel</label>
            <select id="pSel" class="form-select form-select-sm mb-2"></select>
            <div class="row g-2">
                <div class="col-4"><label class="form-label small mb-0">Watt</label><input id="pWatt" type="number" min="50" max="1000" class="form-control form-control-sm"></div>
                <div class="col-4"><label class="form-label small mb-0">Length m</label><input id="pLen" type="number" step="0.001" class="form-control form-control-sm"></div>
                <div class="col-4"><label class="form-label small mb-0">Width m</label><input id="pWid" type="number" step="0.001" class="form-control form-control-sm"></div>
                <div class="col-6"><label class="form-label small mb-0">Orientation</label><select id="pOrient" class="form-select form-select-sm"><option value="portrait">Portrait</option><option value="landscape">Landscape</option></select></div>
                <div class="col-6"><label class="form-label small mb-0">Tilt °</label><input id="pTilt" type="number" min="0" max="60" class="form-control form-control-sm"></div>
                <div class="col-6"><label class="form-label small mb-0">Facing</label><select id="pAz" class="form-select form-select-sm">
                    <option value="180">South</option><option value="150">South-east</option><option value="210">South-west</option><option value="90">East</option><option value="270">West</option><option value="0">North</option></select></div>
                <div class="col-6"><label class="form-label small mb-0">Structure height m</label><input id="pFront" type="number" step="0.1" min="0" max="6" class="form-control form-control-sm" title="Height of the panel's low edge above the roof"></div>
                <div class="col-6"><label class="form-label small mb-0">Edge setback m</label><input id="pSetback" type="number" step="0.05" min="0" class="form-control form-control-sm"></div>
                <div class="col-6"><label class="form-label small mb-0">Obstacle gap m</label><input id="pClear" type="number" step="0.05" min="0" class="form-control form-control-sm"></div>
                <div class="col-12"><div class="form-check form-switch small"><input class="form-check-input" type="checkbox" id="pGapAuto"><label class="form-check-label" for="pGapAuto">Auto row gap (no row shading 9 AM–3 PM in winter)</label></div></div>
                <div class="col-6"><label class="form-label small mb-0">Row gap m</label><input id="pGap" type="number" step="0.05" min="0" class="form-control form-control-sm"></div>
                <div class="col-6 d-flex align-items-end"><button id="btnRefill" class="btn btn-sm btn-outline-primary w-100" type="button"><i class="bi bi-magic"></i> Re-fill roof</button></div>
            </div>
            <div class="form-text">Click a panel on the plan to remove or restore it.</div>
        </div></div>
    </div>

    <!-- ===================== plan / 3D ===================== -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-white py-2 d-flex align-items-center gap-2">
                <ul class="nav nav-pills nav-sm">
                    <li class="nav-item"><button class="nav-link py-1 active" type="button" data-view="plan"><i class="bi bi-grid"></i> Plan</button></li>
                    <li class="nav-item"><button class="nav-link py-1" type="button" data-view="3d"><i class="bi bi-badge-3d"></i> 3D view</button></li>
                </ul>
                <div class="form-check form-switch small ms-2 mb-0" id="heatWrap"><input class="form-check-input" type="checkbox" id="heatOn"><label class="form-check-label" for="heatOn">Shading colours</label></div>
                <span id="modeHint" class="small text-primary ms-auto"></span>
            </div>
            <div class="rd-stage">
                <div id="plan" class="rd-plan"></div>
                <div id="view3d" class="rd-3d" hidden></div>
            </div>
            <div class="card-footer bg-white py-2" id="sunBar">
                <div class="d-flex flex-wrap align-items-center gap-2 small">
                    <i class="bi bi-sun text-warning"></i>
                    <select id="sunMonth" class="form-select form-select-sm" style="width:auto">
                        <?php foreach (['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'] as $i => $m): ?><option value="<?= $i ?>" <?= $i === 11 ? 'selected' : '' ?>><?= $m ?> 21</option><?php endforeach ?>
                    </select>
                    <input id="sunHour" type="range" min="6" max="18" step="0.25" value="10" class="form-range" style="max-width:260px">
                    <span id="sunLabel" class="text-muted" style="min-width:150px"></span>
                    <button id="btnPlay" type="button" class="btn btn-sm btn-outline-secondary"><i class="bi bi-play-fill"></i> Play day</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================== results ===================== -->
    <div class="col-lg-3">
        <div class="card mb-3"><div class="card-header bg-white fw-semibold py-2"><i class="bi bi-lightning-charge"></i> Results</div><div class="card-body py-2">
            <div class="rd-kpis">
                <div><small>Panels</small><b id="resPanels">0</b></div>
                <div><small>System size</small><b id="resKw">0 kW</b></div>
                <div><small>Shading loss</small><b id="resLoss">—</b></div>
                <div><small>Generation / year</small><b id="resKwh">—</b></div>
            </div>
            <div class="small text-muted mt-2" id="resYield"></div>
            <div id="resMonthly" class="mt-2"></div>
            <button id="btnSimulate" type="button" class="btn btn-sm btn-outline-warning w-100 mt-2"><i class="bi bi-brightness-high"></i> Run shading analysis</button>
            <div class="progress mt-2" style="height:6px" hidden id="simProgWrap"><div class="progress-bar bg-warning" id="simProg" style="width:0%"></div></div>
            <div class="form-text" id="simNote">Year-round sun path: shadows from tanks, rooms, trees, the parapet and other rows.</div>
        </div></div>
        <div class="card"><div class="card-body py-2 small text-muted">
            <b class="text-body">How to use</b>
            <ol class="ps-3 mb-0">
                <li>Set the site location (or tap <i class="bi bi-geo-alt"></i> on the roof).</li>
                <li>Shape the roof: quick size, or <b>Draw outline</b>.</li>
                <li>Add the water tank, staircase room, trees.</li>
                <li>Pick the panel; the roof fills itself.</li>
                <li>Check the 3D view and shading, then <b>Save</b>.</li>
                <li><b>Create quotation</b>: kW, kit, generation and the 3D picture go in.</li>
            </ol>
        </div></div>
    </div>
</div>

<div class="modal fade" id="mapModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content">
    <div class="modal-header py-2"><h5 class="modal-title h6"><i class="bi bi-map"></i> Find the house</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body p-2">
        <div id="mapSearchBox" class="mb-2"></div>
        <div class="position-relative"><div id="mapCanvas" style="height:62vh;border-radius:8px;background:#e2e8f0"></div><div class="rd-crosshair"></div></div>
        <div class="small text-muted mt-1" id="mapMsg">Search the address or drag the map so the cross is on the roof.</div>
    </div>
    <div class="modal-footer py-2"><button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button><button type="button" id="mapUse" class="btn btn-primary btn-sm"><i class="bi bi-check2"></i> Use this spot</button></div>
</div></div></div>

<script type="application/json" id="rd-init"><?= json_encode($init, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
<script type="importmap">{"imports": {"three": "<?= base_url('assets/vendor/three/three.module.min.js') ?>", "three/addons/OrbitControls.js": "<?= base_url('assets/vendor/three/OrbitControls.js') ?>"}}</script>
<script type="module" src="<?= base_url('assets/js/roof-designer.js') ?>?v=1"></script>
<?= $this->endSection() ?>
