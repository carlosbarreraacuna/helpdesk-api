<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AssetType;
use App\Models\AssetTypeField;

class AssetTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'laptop', 'display_name' => 'Laptop', 'icon' => 'Laptop', 'is_system' => true,
                'fields' => [
                    ['name' => 'brand',    'display_name' => 'Marca',         'field_type' => 'text',   'is_identifier' => false, 'is_required' => true,  'order_index' => 1],
                    ['name' => 'model',    'display_name' => 'Modelo',        'field_type' => 'text',   'is_identifier' => false, 'is_required' => true,  'order_index' => 2],
                    ['name' => 'ram',      'display_name' => 'RAM',           'field_type' => 'select', 'options' => ['4GB','8GB','16GB','32GB','64GB'], 'is_required' => false, 'order_index' => 3],
                    ['name' => 'storage',  'display_name' => 'Almacenamiento','field_type' => 'select', 'options' => ['128GB SSD','256GB SSD','512GB SSD','1TB SSD','1TB HDD','2TB HDD'], 'is_required' => false, 'order_index' => 4],
                    ['name' => 'cpu',      'display_name' => 'Procesador',    'field_type' => 'text',   'is_required' => false, 'order_index' => 5],
                    ['name' => 'os',       'display_name' => 'Sistema Operativo', 'field_type' => 'select', 'options' => ['Windows 10','Windows 11','Ubuntu','macOS'], 'is_required' => false, 'order_index' => 6],
                ],
            ],
            [
                'name' => 'desktop', 'display_name' => 'Computador de Escritorio', 'icon' => 'Monitor', 'is_system' => true,
                'fields' => [
                    ['name' => 'brand',   'display_name' => 'Marca',          'field_type' => 'text',   'is_required' => true,  'order_index' => 1],
                    ['name' => 'model',   'display_name' => 'Modelo',         'field_type' => 'text',   'is_required' => true,  'order_index' => 2],
                    ['name' => 'ram',     'display_name' => 'RAM',            'field_type' => 'select', 'options' => ['4GB','8GB','16GB','32GB'], 'is_required' => false, 'order_index' => 3],
                    ['name' => 'storage', 'display_name' => 'Almacenamiento', 'field_type' => 'select', 'options' => ['256GB SSD','512GB SSD','1TB HDD','2TB HDD'], 'is_required' => false, 'order_index' => 4],
                    ['name' => 'cpu',     'display_name' => 'Procesador',     'field_type' => 'text',   'is_required' => false, 'order_index' => 5],
                    ['name' => 'os',      'display_name' => 'Sistema Operativo', 'field_type' => 'select', 'options' => ['Windows 10','Windows 11','Ubuntu'], 'is_required' => false, 'order_index' => 6],
                ],
            ],
            [
                'name' => 'monitor', 'display_name' => 'Monitor', 'icon' => 'Monitor', 'is_system' => true,
                'fields' => [
                    ['name' => 'brand',      'display_name' => 'Marca',    'field_type' => 'text',   'is_required' => true,  'order_index' => 1],
                    ['name' => 'size',       'display_name' => 'Tamaño',   'field_type' => 'select', 'options' => ['19"','21"','24"','27"','32"'], 'is_required' => false, 'order_index' => 2],
                    ['name' => 'resolution', 'display_name' => 'Resolución','field_type' => 'select', 'options' => ['HD','Full HD','2K','4K'], 'is_required' => false, 'order_index' => 3],
                ],
            ],
            [
                'name' => 'printer', 'display_name' => 'Impresora', 'icon' => 'Printer', 'is_system' => true,
                'fields' => [
                    ['name' => 'brand', 'display_name' => 'Marca',  'field_type' => 'text',   'is_required' => true,  'order_index' => 1],
                    ['name' => 'model', 'display_name' => 'Modelo', 'field_type' => 'text',   'is_required' => true,  'order_index' => 2],
                    ['name' => 'type',  'display_name' => 'Tipo',   'field_type' => 'select', 'options' => ['Laser','Inkjet','Multifuncional'], 'is_required' => false, 'order_index' => 3],
                ],
            ],
            [
                'name' => 'phone', 'display_name' => 'Teléfono IP', 'icon' => 'Phone', 'is_system' => true,
                'fields' => [
                    ['name' => 'brand',      'display_name' => 'Marca',     'field_type' => 'text', 'is_required' => true,  'order_index' => 1],
                    ['name' => 'extension',  'display_name' => 'Extensión', 'field_type' => 'text', 'is_required' => false, 'order_index' => 2],
                ],
            ],
            [
                'name' => 'switch', 'display_name' => 'Switch de Red', 'icon' => 'Network', 'is_system' => true,
                'fields' => [
                    ['name' => 'brand', 'display_name' => 'Marca',   'field_type' => 'text',   'is_required' => true,  'order_index' => 1],
                    ['name' => 'ports', 'display_name' => 'Puertos', 'field_type' => 'select', 'options' => ['8','16','24','48'], 'is_required' => false, 'order_index' => 2],
                ],
            ],
            [
                'name' => 'ups', 'display_name' => 'UPS', 'icon' => 'Zap', 'is_system' => true,
                'fields' => [
                    ['name' => 'brand',    'display_name' => 'Marca',    'field_type' => 'text',   'is_required' => true,  'order_index' => 1],
                    ['name' => 'capacity', 'display_name' => 'Capacidad','field_type' => 'select', 'options' => ['500VA','750VA','1000VA','1500VA','2000VA'], 'is_required' => false, 'order_index' => 2],
                ],
            ],
            [
                'name' => 'scanner', 'display_name' => 'Escáner', 'icon' => 'ScanLine', 'is_system' => true,
                'fields' => [
                    ['name' => 'brand', 'display_name' => 'Marca',  'field_type' => 'text', 'is_required' => true,  'order_index' => 1],
                    ['name' => 'model', 'display_name' => 'Modelo', 'field_type' => 'text', 'is_required' => false, 'order_index' => 2],
                ],
            ],
            [
                'name' => 'other', 'display_name' => 'Otro', 'icon' => 'Package', 'is_system' => true,
                'fields' => [],
            ],
        ];

        foreach ($types as $typeData) {
            $fields = $typeData['fields'];
            unset($typeData['fields']);

            $type = AssetType::firstOrCreate(['name' => $typeData['name']], $typeData);

            foreach ($fields as $field) {
                AssetTypeField::firstOrCreate(
                    ['asset_type_id' => $type->id, 'name' => $field['name']],
                    $field
                );
            }
        }
    }
}
