<?php

namespace App\Controllers\Admin;

use App\Libraries\Numbering;
use App\Libraries\SolarYield;
use App\Libraries\Workflow;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * 3D roof designs: draw the roof, obstacles and panel layout in the browser
 * (app/Views/admin/designs/editor.php), simulate shading and generation, save,
 * and turn the design into a quotation.
 */
class RoofDesigns extends AdminController
{
    public function index()
    {
        $this->requirePermission('designs.view', 'designs.manage');
        $b = db_connect()->table('roof_designs d')
            ->select("d.*, l.name AS lead_name, q.quote_no, (SELECT COUNT(*) FROM " . db_connect()->prefixTable('roof_design_photos') . " ph WHERE ph.design_id = d.id AND ph.kind = 'site') AS photo_count", false)
            ->join('leads l', 'l.id = d.lead_id', 'left')
            ->join('quotations q', 'q.id = d.quotation_id', 'left')
            ->where('d.deleted_at', null);
        if ($lead = (int) $this->request->getGet('lead')) {
            $b->where('d.lead_id', $lead);
        }
        if ($term = trim((string) $this->request->getGet('q'))) {
            $b->groupStart()->like('d.design_no', $term)->orLike('d.name', $term)->orLike('l.name', $term)->groupEnd();
        }

        return $this->render('designs/index', ['rows' => $b->orderBy('d.id', 'DESC')->get()->getResultArray()]);
    }

    public function create()
    {
        $this->requirePermission('designs.manage');
        $design = ['id' => null, 'name' => '', 'lead_id' => null, 'customer_id' => null, 'latitude' => null, 'longitude' => null, 'data' => null];
        if ($leadId = (int) $this->request->getGet('lead')) {
            $l      = $this->findOr404('leads', $leadId);
            $design = array_merge($design, ['lead_id' => $l['id'], 'customer_id' => $l['customer_id'],
                'name' => trim($l['name'] . ($l['required_kw'] > 0 ? ' · ' . (float) $l['required_kw'] . ' kW' : '')),
                'address' => trim(($l['address'] ?? '') . ' ' . ($l['city'] ?? ''))]);
        }

        return $this->editor($design);
    }

    public function edit($id)
    {
        $this->requirePermission('designs.view', 'designs.manage');

        return $this->editor($this->design((int) $id));
    }

    /** Save from the editor (JSON body). Answers JSON. */
    public function save($id = null)
    {
        $this->requirePermission('designs.manage');
        $in = $this->request->getJSON(true) ?: [];
        $db = db_connect();
        $existing = $id ? $this->design((int) $id) : null;

        $name = trim((string) ($in['name'] ?? ''));
        if ($name === '') {
            return $this->fail('Give the design a name (e.g. the customer and site).');
        }
        $data = $in['data'] ?? null;
        $json = is_array($data) ? json_encode($data) : null;
        if ($json === null || strlen($json) > 2_000_000) {
            return $this->fail('The design data is missing or too large.');
        }
        $lat   = is_numeric($in['latitude'] ?? null) ? (float) $in['latitude'] : null;
        $lng   = is_numeric($in['longitude'] ?? null) ? (float) $in['longitude'] : null;
        $count = max(0, (int) ($in['panel_count'] ?? 0));
        $watt  = max(0.0, (float) ($in['panel_watt'] ?? 0));
        $kw    = round($count * $watt / 1000, 3);
        $kwp   = max(0.0, (float) ($in['kwh_per_kwp'] ?? 0));
        $loss  = max(0.0, min(100.0, (float) ($in['shading_loss'] ?? 0)));
        $monthly = array_slice(array_map('floatval', (array) ($in['monthly_kwh'] ?? [])), 0, 12);
        $leadId  = (int) ($in['lead_id'] ?? 0) ?: null;
        $customerId = null;
        if ($leadId && ($l = $db->table('leads')->select('customer_id')->where('id', $leadId)->get()->getRowArray())) {
            $customerId = $l['customer_id'];
        }

        $row = [
            'name' => mb_substr($name, 0, 150), 'lead_id' => $leadId, 'customer_id' => $customerId,
            'latitude' => $lat, 'longitude' => $lng, 'data' => $json,
            'panel_item_id' => (int) ($in['panel_item_id'] ?? 0) ?: null, 'panel_watt' => $watt, 'panel_count' => $count,
            'capacity_kw' => $kw, 'kwh_per_kwp' => $kwp, 'shading_loss' => round($loss, 2),
            'kwh_year' => round($kw * $kwp * (1 - $loss / 100), 1),
            'monthly_kwh' => json_encode($monthly), 'yield_source' => in_array($in['yield_source'] ?? '', ['pvgis', 'estimate'], true) ? $in['yield_source'] : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($existing) {
            $db->table('roof_designs')->where('id', $existing['id'])->update($row);
            $designId = (int) $existing['id'];
        } else {
            $db->table('roof_designs')->insert($row + [
                'design_no' => Numbering::next('roof_designs', 'design_no', (string) (setting('Solar.designPrefix') ?: 'RD')),
                'created_by' => auth()->id(), 'created_at' => date('Y-m-d H:i:s'),
            ]);
            $designId = (int) $db->insertID();
            Workflow::log('design', $designId, 'created', 'Roof design created: ' . $row['name'] . ' (' . $kw . ' kW)');
        }
        if (! empty($in['snapshot'])) {
            $this->saveSnapshot($designId, (string) $in['snapshot'], $existing['snapshot_path'] ?? null);
        }
        // Photos uploaded before the first save belong to this design now.
        $ids = array_filter(array_map('intval', (array) ($in['photo_ids'] ?? [])));
        if ($ids) {
            $db->table('roof_design_photos')->whereIn('id', $ids)->where('design_id', null)->where('created_by', auth()->id())
                ->update(['design_id' => $designId]);
        }
        $saved = $this->design($designId);

        return $this->response->setJSON(['ok' => true, 'id' => $designId, 'design_no' => $saved['design_no'],
            'capacity_kw' => (float) $saved['capacity_kw'], 'kwh_year' => (float) $saved['kwh_year'],
            'url' => site_url('admin/designs/' . $designId)]);
    }

    public function delete($id)
    {
        $this->requirePermission('designs.manage');
        $d = $this->design((int) $id);
        db_connect()->table('roof_designs')->where('id', $d['id'])->update(['deleted_at' => date('Y-m-d H:i:s')]);
        Workflow::log('design', (int) $d['id'], 'deleted', 'Roof design ' . $d['design_no'] . ' deleted');

        return redirect()->to('admin/designs')->with('message', 'Design ' . $d['design_no'] . ' deleted.');
    }

    /** kWh per kWp for a location / tilt / direction (AJAX). */
    public function yield()
    {
        $this->requirePermission('designs.view', 'designs.manage');
        $g   = $this->request->getGet();
        $lat = is_numeric($g['lat'] ?? null) ? (float) $g['lat'] : null;
        $lng = is_numeric($g['lng'] ?? null) ? (float) $g['lng'] : null;

        return $this->response->setJSON(SolarYield::get($lat, $lng, (float) ($g['tilt'] ?? 10), (float) ($g['azimuth'] ?? 180)));
    }

    /** The 3D picture saved with the design. */
    public function snapshot($id)
    {
        $this->requirePermission('designs.view', 'designs.manage', 'quotations.view');
        $d    = $this->design((int) $id);
        $path = upload_path() . $d['snapshot_path'];
        if (! $d['snapshot_path'] || ! is_file($path)) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response->setHeader('Content-Type', 'image/png')->setHeader('Cache-Control', 'private, max-age=300')->setBody(file_get_contents($path));
    }

    /** "Create quotation" from the design: the quotation form, pre-filled. */
    public function quotation($id)
    {
        $this->requirePermission('quotations.manage');
        $d = $this->design((int) $id);
        if ($d['panel_count'] < 1) {
            return redirect()->to('admin/designs/' . $d['id'])->with('error', 'Place at least one panel before making a quotation.');
        }

        return redirect()->to('admin/quotations/new?design=' . $d['id'] . ($d['lead_id'] ? '&lead=' . $d['lead_id'] : ''));
    }

    /** Upload a photo: 'plan' (top view to trace) or 'site' (reference). Answers JSON. */
    public function photoUpload()
    {
        $this->requirePermission('designs.manage');
        $file = $this->request->getFile('photo');
        if ($file === null || ! $file->isValid()) {
            return $this->fail('Choose a photo to upload.');
        }
        if ($file->getSizeByUnit('mb') > 25) {
            return $this->fail('The photo is larger than 25 MB.');
        }
        $info = @getimagesize($file->getTempName());
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return $this->fail('Only JPG, PNG or WEBP photos. (iPhone: Settings → Camera → Formats → Most Compatible.)');
        }
        $kind = $this->request->getPost('kind') === 'plan' ? 'plan' : 'site';
        $designId = (int) $this->request->getPost('design_id') ?: null;
        if ($designId && ! db_connect()->table('roof_designs')->where(['id' => $designId, 'deleted_at' => null])->countAllResults()) {
            $designId = null;
        }
        [$img, $gps] = $this->readPhoto($file->getTempName(), $info[2]);
        if (! $img) {
            return $this->fail('This photo could not be read.');
        }
        $img = $this->limitSize($img, $kind === 'plan' ? 2600 : 1800);
        $dir = upload_path() . 'designs/photos';
        is_dir($dir) || mkdir($dir, 0775, true);
        $rel = 'designs/photos/' . date('Ymd-His') . '-' . bin2hex(random_bytes(5)) . '.jpg';
        imagejpeg($img, upload_path() . $rel, 85);
        $w = imagesx($img);
        $h = imagesy($img);
        imagedestroy($img);
        $db = db_connect();
        $db->table('roof_design_photos')->insert([
            'design_id' => $designId, 'kind' => $kind, 'path' => $rel,
            'caption' => mb_substr(trim((string) $this->request->getPost('caption')), 0, 150) ?: null,
            'width' => $w, 'height' => $h, 'latitude' => $gps[0] ?? null, 'longitude' => $gps[1] ?? null,
            'created_by' => auth()->id(), 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $row = $db->table('roof_design_photos')->where('id', $db->insertID())->get()->getRowArray();

        return $this->response->setJSON(['ok' => true] + $this->photoJson($row));
    }

    public function photo($id)
    {
        $this->requirePermission('designs.view', 'designs.manage', 'quotations.view');
        $ph   = db_connect()->table('roof_design_photos')->where('id', (int) $id)->get()->getRowArray();
        $path = $ph ? upload_path() . $ph['path'] : '';
        if (! $ph || ! is_file($path) || ($ph['design_id'] === null && (int) $ph['created_by'] !== (int) auth()->id())) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response->setHeader('Content-Type', 'image/jpeg')->setHeader('Cache-Control', 'private, max-age=86400')->setBody(file_get_contents($path));
    }

    public function photoDelete($id)
    {
        $this->requirePermission('designs.manage');
        $db = db_connect();
        $ph = $db->table('roof_design_photos')->where('id', (int) $id)->get()->getRowArray();
        if (! $ph || ($ph['design_id'] === null && (int) $ph['created_by'] !== (int) auth()->id())) {
            return $this->fail('Photo not found.');
        }
        @unlink(upload_path() . $ph['path']);
        $db->table('roof_design_photos')->where('id', $ph['id'])->delete();

        return $this->response->setJSON(['ok' => true]);
    }

    // ------------------------------------------------------------------

    private function photoJson(array $ph): array
    {
        return ['id' => (int) $ph['id'], 'kind' => $ph['kind'], 'url' => site_url('admin/designs/photos/' . $ph['id']),
            'w' => (int) $ph['width'], 'h' => (int) $ph['height'], 'caption' => (string) $ph['caption'],
            'lat' => $ph['latitude'] !== null ? (float) $ph['latitude'] : null, 'lng' => $ph['longitude'] !== null ? (float) $ph['longitude'] : null];
    }

    /** GD image, turned upright using the camera's EXIF orientation, plus the GPS position if the photo has one. */
    private function readPhoto(string $file, int $type): array
    {
        $img = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file),
            IMAGETYPE_PNG  => @imagecreatefrompng($file),
            IMAGETYPE_WEBP => @imagecreatefromwebp($file),
            default        => false,
        };
        $gps = [];
        if ($img && $type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($file);
            $rot  = [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
            if ($rot) {
                $img = imagerotate($img, $rot, 0);
            }
            $deg = static function ($v, $ref) {
                if (! is_array($v) || count($v) < 3) {
                    return null;
                }
                $f = static fn ($x) => str_contains((string) $x, '/') ? (static fn ($p) => $p[1] ? $p[0] / $p[1] : 0)(array_map('floatval', explode('/', $x))) : (float) $x;
                $d = $f($v[0]) + $f($v[1]) / 60 + $f($v[2]) / 3600;

                return in_array($ref, ['S', 'W'], true) ? -$d : $d;
            };
            $lat = $deg($exif['GPSLatitude'] ?? null, $exif['GPSLatitudeRef'] ?? 'N');
            $lng = $deg($exif['GPSLongitude'] ?? null, $exif['GPSLongitudeRef'] ?? 'E');
            if ($lat !== null && $lng !== null && ($lat != 0.0 || $lng != 0.0)) {
                $gps = [round($lat, 6), round($lng, 6)];
            }
        }
        if ($img && $type === IMAGETYPE_PNG) {
            // flatten transparency onto white so screenshots look right as JPEG
            $bg = imagecreatetruecolor(imagesx($img), imagesy($img));
            imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
            imagecopy($bg, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
            imagedestroy($img);
            $img = $bg;
        }

        return [$img ?: null, $gps];
    }

    private function limitSize($img, int $max)
    {
        $w = imagesx($img);
        $h = imagesy($img);
        if (max($w, $h) <= $max) {
            return $img;
        }
        $k  = $max / max($w, $h);
        $nw = (int) round($w * $k);
        $nh = (int) round($h * $k);
        $out = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);

        return $out;
    }

    private function editor(array $design): string
    {
        $db = db_connect();
        // Panels = items whose name carries a wattage, e.g. "Mono PERC Panel 545W".
        $panels = [];
        foreach ($db->table('items')->select('id, name')->where(['is_active' => 1, 'deleted_at' => null])->orderBy('name')->get()->getResultArray() as $it) {
            if (preg_match('/(?<!\d)(\d{3,4})\s*W(?:p)?\b/i', $it['name'], $m) && (int) $m[1] >= 100 && (int) $m[1] <= 1000) {
                $panels[] = ['id' => (int) $it['id'], 'name' => $it['name'], 'watt' => (int) $m[1]];
            }
        }
        $lead = $design['lead_id'] ? $db->table('leads')->select('id, name, phone, address, city, required_kw')->where('id', $design['lead_id'])->get()->getRowArray() : null;

        $photos = $design['id'] ? $db->table('roof_design_photos')->where('design_id', $design['id'])->orderBy('id')->get()->getResultArray() : [];

        return $this->render('designs/editor', [
            'design'   => $design,
            'photos'   => array_map(fn ($ph) => $this->photoJson($ph), $photos),
            'mapsKey'  => trim((string) setting('Solar.googleMapsKey')) ?: trim((string) env('google.mapsKey', '')),
            'lead'     => $lead,
            'panels'   => $panels,
            'canEdit'  => can('designs.manage'),
            'leads'    => $db->table('leads')->select('id, name, city')->where('deleted_at', null)->whereNotIn('status', ['lost'])->orderBy('id', 'DESC')->limit(300)->get()->getResultArray(),
            'quotation'=> ! empty($design['quotation_id']) ? $db->table('quotations')->select('id, quote_no')->where('id', $design['quotation_id'])->get()->getRowArray() : null,
        ]);
    }

    private function design(int $id): array
    {
        $d = db_connect()->table('roof_designs')->where(['id' => $id, 'deleted_at' => null])->get()->getRowArray();
        if (! $d) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $d;
    }

    private function fail(string $message)
    {
        return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => $message]);
    }

    /** data:image/png;base64,… → uploads/designs/<id>-<time>.png */
    private function saveSnapshot(int $id, string $dataUrl, ?string $old): void
    {
        if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) {
            return;
        }
        $png = base64_decode($m[1], true);
        if ($png === false || strlen($png) > 6_000_000 || substr($png, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            return;
        }
        $dir = upload_path() . 'designs';
        is_dir($dir) || mkdir($dir, 0775, true);
        $rel = 'designs/' . $id . '-' . time() . '.png';
        file_put_contents(upload_path() . $rel, $png);
        db_connect()->table('roof_designs')->where('id', $id)->update(['snapshot_path' => $rel]);
        if ($old && $old !== $rel) {
            @unlink(upload_path() . $old);
        }
    }
}
