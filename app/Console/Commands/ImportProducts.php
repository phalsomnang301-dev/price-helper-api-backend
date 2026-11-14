<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportProducts extends Command
{
    protected $signature = 'products:import {file}';
    protected $description = 'Import products and prices from a CSV file';

    public function handle(): int
    {
        $path = $this->argument('file');
        if (! is_file($path)) {
            $this->error("រកមិនឃើញឯកសារ: $path");
            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]); // លុប BOM ពី Excel
        $header = array_map('trim', $header);

        // ---------- 1) អាន + ពិនិត្យជាមុន (មិនទាន់សរសេរអ្វីក្នុង DB) ----------
        $rows = [];
        $errors = [];
        $line = 1;

        while (($cols = fgetcsv($handle)) !== false) {
            $line++;
            if (count(array_filter($cols, fn ($c) => trim($c) !== '')) === 0) {
                continue; // ជួរទទេ
            }
            if (count($cols) !== count($header)) {
                $errors[] = "ជួរ $line: ចំនួនជួរឈរមិនត្រូវ";
                continue;
            }

            $row = array_combine($header, array_map('trim', $cols));
            $price = str_replace(',', '', $row['price'] ?? '');

            if (($row['category'] ?? '') === '') $errors[] = "ជួរ $line: ខ្វះ category";
            if (($row['name'] ?? '') === '')     $errors[] = "ជួរ $line: ខ្វះ name";
            if (($row['unit'] ?? '') === '')     $errors[] = "ជួរ $line: ខ្វះ unit";
            if (! is_numeric($price))            $errors[] = "ជួរ $line: តម្លៃមិនមែនលេខ";

            $row['price'] = $price;
            $row['currency'] = strtoupper($row['currency'] ?? '') ?: 'KHR';
            if (! in_array($row['currency'], ['KHR', 'USD'])) {
                $errors[] = "ជួរ $line: currency ត្រូវជា KHR ឬ USD";
            }
            $rows[] = $row;
        }
        fclose($handle);

        if ($errors) {
            foreach ($errors as $e) $this->error($e);
            $this->warn('គ្មានអ្វីត្រូវបានរក្សាទុកទេ។ សូមកែ CSV ហើយរត់ម្តងទៀត។');
            return self::FAILURE;
        }

        // ---------- 2) រក្សាទុក (ទាំងអស់ ឬមិនធ្វើសោះ) ----------
        $groups = collect($rows)->groupBy(fn ($r) => $r['category'].'|'.$r['name']);

        DB::transaction(function () use ($groups) {
            foreach ($groups as $items) {
                $first = $items->first();

                $category = Category::firstOrCreate(['name' => $first['category']]);

                $product = Product::updateOrCreate(
                    ['category_id' => $category->id, 'name' => $first['name']],
                    ['note' => $first['note'] ?: null],
                );

                $product->prices()->delete(); // CSV ជាប្រភពពិត
                $product->prices()->createMany(
                    $items->map(fn ($r) => [
                        'unit' => $r['unit'],
                        'price' => $r['price'],
                        'currency' => $r['currency'],
                    ])->all()
                );
            }
        });

        $this->info('Import ជោគជ័យ: '.$groups->count().' ទំនិញ, '.count($rows).' តម្លៃ');
        return self::SUCCESS;
    }
}