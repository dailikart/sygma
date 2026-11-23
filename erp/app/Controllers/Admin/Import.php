<?php

namespace App\Controllers\Admin;

use App\Libraries\Stock;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

/**
 * Bulk upload from Excel / CSV for items, customers, suppliers and
 * income / expense heads.
 *
 * All rows are checked first; if any row has an error nothing is saved.
 * Existing records are skipped, or updated when "Update existing" is ticked.
 */
class Import extends AdminController
{
    /** Column definitions: key => [heading, required, example]. */
    private function defs(): array
    {
        return [
            'items' => [
                'title' => 'Items', 'perm' => 'inventory.manage', 'back' => 'admin/items',
                'cols'  => [
                    'name' => ['Item name', true, 'Mono PERC Panel 545W'], 'sku' => ['Item code / SKU', false, 'PNL545'],
                    'installation_type' => ['Installation type', false, 'On-grid'], 'category' => ['Category', true, 'Solar Panel'],
                    'unit' => ['Unit', true, 'Nos'], 'hsn' => ['HSN / SAC', false, '8541'], 'tax_percent' => ['GST %', false, '12'],
                    'purchase_price' => ['Purchase price', false, '9000'], 'sale_price' => ['Sale price', false, '11000'],
                    'min_stock' => ['Minimum stock', false, '10'], 'opening_location' => ['Opening stock location', false, 'Main Store'],
                    'opening_qty' => ['Opening stock qty', false, '25'],
                ],
            ],
            'customers' => [
                'title' => 'Customers', 'perm' => 'customers.manage', 'back' => 'admin/customers',
                'cols'  => [
                    'type' => ['Type (B2B / B2C)', true, 'B2C'], 'name' => ['Name', true, 'Murugan S'], 'company' => ['Company', false, ''],
                    'gstin' => ['GSTIN', false, ''], 'phone' => ['Phone', true, '9876543210'], 'email' => ['Email', false, 'murugan@example.com'],
                    'address' => ['Address', false, '12, Gandhi Nagar'], 'city' => ['City', false, 'Erode'], 'state' => ['State', false, 'Tamil Nadu'],
                    'pincode' => ['PIN code', false, '638001'], 'eb_service_no' => ['EB service no.', false, '04-123-456'],
                ],
            ],
            'suppliers' => [
                'title' => 'Suppliers', 'perm' => 'suppliers.manage', 'back' => 'admin/suppliers',
                'cols'  => [
                    'name' => ['Supplier name', true, 'ABC Solar Distributors'], 'contact_person' => ['Contact person', false, 'Ravi'],
                    'phone' => ['Phone', false, '9800000001'], 'email' => ['Email', false, ''], 'gstin' => ['GSTIN', false, '33ABCDE1234F1Z5'],
                    'state' => ['State', false, 'Tamil Nadu'], 'address' => ['Address', false, 'Coimbatore'],
                    'opening_balance' => ['Opening balance we owe', false, '0'],
                ],
            ],
            'income-heads' => [
                'title' => 'Income Heads', 'perm' => 'accounts.manage', 'back' => 'admin/income-heads', 'table' => 'income_heads',
                'cols'  => ['name' => ['Income head', true, 'Scrap Sale'], 'description' => ['Description', false, '']],
            ],
            'expense-heads' => [
                'title' => 'Expense Heads', 'perm' => 'accounts.manage', 'back' => 'admin/expense-heads', 'table' => 'expense_heads',
                'cols'  => ['name' => ['Expense head', true, 'Fuel Expenses'], 'description' => ['Description', false, '']],
            ],
        ];
    }

    private function def(string $entity): array
    {
        $defs = $this->defs();
        if (! isset($defs[$entity])) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $this->requirePermission($defs[$entity]['perm']);

        return $defs[$entity];
    }

    public function form($entity)
    {
        $def = $this->def($entity);
        if ($this->request->getGet('template')) {
            return $this->template($entity, $def);
        }

        return $this->render('import', ['entity' => $entity, 'def' => $def, 'result' => session()->getFlashdata('import_result')]);
    }

    /** Excel template with headings, a sample row and a sheet of valid values. */
    private function template(string $entity, array $def)
    {
        $book  = new Spreadsheet();
        $sheet = $book->getActiveSheet()->setTitle($def['title']);
        $c     = 1;
        foreach ($def['cols'] as [$heading, $required, $example]) {
            $sheet->setCellValue([$c, 1], $heading . ($required ? ' *' : ''));
            $sheet->setCellValue([$c, 2], $example);
            $sheet->getStyle([$c, 1])->getFont()->setBold(true);
            $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
            $c++;
        }
        if ($entity === 'items') {
            $ref = $book->createSheet()->setTitle('Valid values');
            $ref->fromArray([['Installation types', 'Categories', 'Stock locations', 'Units']], null, 'A1');
            $db  = db_connect();
            $cols = [
                array_column($db->table('installation_types')->select('name')->get()->getResultArray(), 'name'),
                array_column($db->table('item_categories')->select('name')->get()->getResultArray(), 'name'),
                array_column($db->table('locations')->select('name')->get()->getResultArray(), 'name'),
                ['Nos', 'Mtr', 'Set', 'Kg', 'Box', 'Pair', 'Lot', 'kW', 'Pieces'],
            ];
            foreach ($cols as $i => $vals) {
                foreach ($vals as $r => $v) {
                    $ref->setCellValue([$i + 1, $r + 2], $v);
                }
                $ref->getColumnDimensionByColumn($i + 1)->setAutoSize(true);
            }
            $ref->setCellValue('F1', 'New installation types / categories in your file are created automatically. Locations must already exist.');
        }
        $book->setActiveSheetIndex(0);
        $tmp = WRITEPATH . 'cache/import-template-' . bin2hex(random_bytes(4)) . '.xlsx';
        (new Xlsx($book))->save($tmp);
        $body = file_get_contents($tmp);
        @unlink($tmp);

        return $this->response->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . str_replace(' ', '_', $def['title']) . '_upload_template.xlsx"')
            ->setBody($body);
    }

    public function upload($entity)
    {
        $def  = $this->def($entity);
        $file = $this->request->getFile('file');
        if (! $file || ! $file->isValid()) {
            return $this->back('Choose the Excel / CSV file to upload.');
        }
        $ext = strtolower($file->getClientExtension());
        if (! in_array($ext, ['xlsx', 'xls', 'csv'], true) || $file->getSizeByUnit('mb') > 5) {
            return $this->back('Upload an .xlsx, .xls or .csv file up to 5 MB.');
        }

        try {
            $rows = $this->readRows($file->getTempName(), $ext, $def['cols']);
        } catch (RuntimeException $e) {
            return $this->back($e->getMessage());
        }
        if (! $rows) {
            return $this->back('The file has no data rows.');
        }
        $update = (bool) $this->request->getPost('update_existing');

        [$errors, $prepared] = $this->validateRows($entity, $rows);
        if ($errors) {
            return redirect()->back()->with('import_result', ['ok' => false, 'errors' => array_slice($errors, 0, 200), 'count' => count($rows)]);
        }

        $db = db_connect();
        $db->transException(true)->transStart();
        try {
            $stats = $this->save($entity, $def, $prepared, $update);
            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();

            return $this->back('Nothing was saved: ' . $e->getMessage());
        }
        \App\Libraries\Workflow::log('import', 0, $entity, sprintf('%s bulk upload: %d added, %d updated, %d skipped', $def['title'], $stats['added'], $stats['updated'], $stats['skipped']));

        return redirect()->back()->with('import_result', ['ok' => true] + $stats);
    }

    /** Reads the first sheet; maps columns by heading (case/asterisk insensitive). */
    private function readRows(string $path, string $ext, array $cols): array
    {
        $reader = IOFactory::createReader(match ($ext) { 'csv' => 'Csv', 'xls' => 'Xls', default => 'Xlsx' });
        $reader->setReadDataOnly(true);
        $data = $reader->load($path)->getSheet(0)->toArray(null, true, false, false);
        if (! $data) {
            return [];
        }
        $norm = static fn ($h) => preg_replace('/[^a-z0-9]+/', '', strtolower(str_replace('*', '', (string) $h)));
        $map  = [];
        foreach ($cols as $key => [$heading]) {
            $map[$norm($heading)] = $key;
            $map[$norm($key)]     = $key;
        }
        $index = [];
        foreach ($data[0] as $i => $h) {
            if (isset($map[$norm($h)])) {
                $index[$i] = $map[$norm($h)];
            }
        }
        foreach ($cols as $key => [$heading, $required]) {
            if ($required && ! in_array($key, $index, true)) {
                throw new RuntimeException('Column "' . $heading . '" is missing. Download the template and keep its headings.');
            }
        }
        $rows = [];
        foreach (array_slice($data, 1) as $n => $line) {
            $row = [];
            foreach ($index as $i => $key) {
                $row[$key] = trim((string) ($line[$i] ?? ''));
            }
            if (implode('', $row) === '') {
                continue; // blank line
            }
            $rows[$n + 2] = $row; // Excel row number
        }

        return $rows;
    }

    private function validateRows(string $entity, array $rows): array
    {
        $errors = [];
        $def    = $this->defs()[$entity];
        $db     = db_connect();
        $locs   = array_change_key_case(array_column($db->table('locations')->select('id, name')->get()->getResultArray(), 'id', 'name'));
        $seen   = [];
        foreach ($rows as $n => &$r) {
            foreach ($def['cols'] as $key => [$heading, $required]) {
                if ($required && ($r[$key] ?? '') === '') {
                    $errors[] = "Row {$n}: {$heading} is required.";
                }
            }
            foreach (['tax_percent', 'purchase_price', 'sale_price', 'min_stock', 'opening_qty', 'opening_balance'] as $num) {
                if (($r[$num] ?? '') !== '') {
                    $v = str_replace([',', '₹', '%', ' '], '', $r[$num]);
                    if (! is_numeric($v)) {
                        $errors[] = "Row {$n}: {$def['cols'][$num][0]} must be a number.";
                    }
                    $r[$num] = (float) $v;
                }
            }
            if (! empty($r['email']) && ! filter_var($r['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Row {$n}: email \"{$r['email']}\" is not valid.";
            }
            if (! empty($r['gstin']) && strlen(preg_replace('/\s+/', '', $r['gstin'])) !== 15) {
                $errors[] = "Row {$n}: GSTIN must be 15 characters.";
            }
            if ($entity === 'customers') {
                $t = strtolower($r['type'] ?? '');
                if (! in_array($t, ['b2b', 'b2c'], true)) {
                    $errors[] = "Row {$n}: Type must be B2B or B2C.";
                }
                $r['type'] = $t;
            }
            if ($entity === 'items') {
                if (! empty($r['opening_qty']) && $r['opening_qty'] > 0) {
                    if (($r['opening_location'] ?? '') === '' || ! isset($locs[strtolower($r['opening_location'])])) {
                        $errors[] = "Row {$n}: opening stock location \"" . ($r['opening_location'] ?? '') . '" not found. Use a store name from Inventory → Locations.';
                    } else {
                        $r['location_id'] = (int) $locs[strtolower($r['opening_location'])];
                    }
                }
            }
            $dupKey = strtolower(($entity === 'customers' ? ($r['phone'] ?? '') . '|' : '') . ($r['sku'] ?? '') . '|' . ($r['name'] ?? ''));
            if (isset($seen[$dupKey])) {
                $errors[] = "Row {$n}: same as row {$seen[$dupKey]} (duplicate in the file).";
            }
            $seen[$dupKey] = $n;
        }
        unset($r);

        return [$errors, $rows];
    }

    private function save(string $entity, array $def, array $rows, bool $update): array
    {
        $db    = db_connect();
        $now   = date('Y-m-d H:i:s');
        $stats = ['added' => 0, 'updated' => 0, 'skipped' => 0, 'created' => []];
        $stamp = ['created_by' => auth()->id(), 'created_at' => $now, 'updated_at' => $now];
        $null  = static fn ($v) => ($v === '' || $v === null) ? null : $v;

        foreach ($rows as $r) {
            switch ($entity) {
                case 'items':
                    $typeId = null;
                    if (($r['installation_type'] ?? '') !== '') {
                        $typeId = $this->findOrCreate('installation_types', $r['installation_type'], [], $stats);
                    }
                    $catId = $this->findOrCreate('item_categories', $r['category'], ['installation_type_id' => $typeId], $stats);
                    $data  = [
                        'name' => $r['name'], 'sku' => $null($r['sku'] ?? ''), 'installation_type_id' => $typeId, 'category_id' => $catId,
                        'unit' => $r['unit'], 'hsn' => $null($r['hsn'] ?? ''), 'tax_percent' => (float) ($r['tax_percent'] ?? 0),
                        'purchase_price' => (float) ($r['purchase_price'] ?? 0), 'sale_price' => (float) ($r['sale_price'] ?? 0),
                        'min_stock' => (float) ($r['min_stock'] ?? 0), 'is_active' => 1,
                    ];
                    $b = $db->table('items')->where('deleted_at', null);
                    ($data['sku'] ? $b->where('sku', $data['sku']) : $b->where('name', $data['name']));
                    $existing = $b->get()->getRowArray();
                    $id = $this->upsert('items', $existing, $data, $stamp, $update, $stats);
                    if ($id && ! $existing && ! empty($r['opening_qty']) && $r['opening_qty'] > 0) {
                        Stock::move($id, (int) $r['location_id'], (float) $r['opening_qty'], 'opening', 'adjustment', null, 'Opening stock (bulk upload)');
                    }
                    break;

                case 'customers':
                    $data = ['type' => $r['type'], 'name' => $r['name']];
                    foreach (['company', 'gstin', 'phone', 'email', 'address', 'city', 'state', 'pincode', 'eb_service_no'] as $f) {
                        $data[$f] = $null($r[$f] ?? '');
                    }
                    $data['gstin'] = $data['gstin'] ? strtoupper(preg_replace('/\s+/', '', $data['gstin'])) : null;
                    $data['state'] ??= (string) setting('Solar.companyState');
                    $existing = $db->table('customers')->where(['phone' => $data['phone'], 'name' => $data['name'], 'deleted_at' => null])->get()->getRowArray();
                    $this->upsert('customers', $existing, $data, $stamp, $update, $stats);
                    break;

                case 'suppliers':
                    $data = ['name' => $r['name'], 'opening_balance' => (float) ($r['opening_balance'] ?? 0), 'is_active' => 1];
                    foreach (['contact_person', 'phone', 'email', 'gstin', 'state', 'address'] as $f) {
                        $data[$f] = $null($r[$f] ?? '');
                    }
                    $existing = $db->table('suppliers')->where(['name' => $data['name'], 'deleted_at' => null])->get()->getRowArray();
                    $id = $this->upsert('suppliers', $existing, $data, $stamp, $update, $stats);
                    if ($existing && $update) { // keep the supplier ledger's opening balance in step
                        $db->table('ledgers')->where(['ref_type' => 'supplier', 'ref_id' => $id])->update(['opening_balance' => -$data['opening_balance']]);
                    }
                    break;

                default: // income / expense heads
                    $data     = ['name' => $r['name'], 'description' => $null($r['description'] ?? ''), 'is_active' => 1];
                    $existing = $db->table($def['table'])->where('name', $data['name'])->get()->getRowArray();
                    $this->upsert($def['table'], $existing, $data, $stamp, $update, $stats);
            }
        }

        return $stats;
    }

    private function upsert(string $table, ?array $existing, array $data, array $stamp, bool $update, array &$stats): ?int
    {
        $db = db_connect();
        if ($existing) {
            if (! $update) {
                $stats['skipped']++;

                return (int) $existing['id'];
            }
            $db->table($table)->where('id', $existing['id'])->update($data + ['updated_at' => $stamp['updated_at']]);
            $stats['updated']++;

            return (int) $existing['id'];
        }
        $db->table($table)->insert($data + $stamp);
        $stats['added']++;

        return (int) $db->insertID();
    }

    private function findOrCreate(string $table, string $name, array $extra, array &$stats): int
    {
        $db  = db_connect();
        $row = $db->table($table)->select('id')->where('name', $name)->get()->getRowArray();
        if ($row) {
            return (int) $row['id'];
        }
        $now = date('Y-m-d H:i:s');
        $db->table($table)->insert(['name' => $name, 'is_active' => 1, 'created_by' => auth()->id(), 'created_at' => $now, 'updated_at' => $now] + $extra);
        $stats['created'][] = ($table === 'installation_types' ? 'Installation type' : 'Category') . ': ' . $name;

        return (int) $db->insertID();
    }
}
