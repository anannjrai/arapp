<?php

namespace Database\Seeders;

use App\Models\BankFileFormat;
use App\Models\BankCountry;
use App\Models\CountryReasonCode;
use App\Models\MasterField;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Payment Admin',
                'username' => 'admin',
                'password' => 'password',
                'role' => User::ROLE_ADMIN,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        foreach ($this->bankCountries() as $country) {
            BankCountry::updateOrCreate(
                ['country_key' => BankCountry::normalizeName($country['country_name'])],
                [
                    'country_name' => $country['country_name'],
                    'country_key' => BankCountry::normalizeName($country['country_name']),
                    'capital' => $country['capital'],
                    'is_active' => true,
                ],
            );
        }

        foreach ($this->countryReasonCodes() as $code) {
            CountryReasonCode::updateOrCreate(
                ['country_code' => $code['country_code'], 'reason_code' => $code['reason_code']],
                $code,
            );
        }

        foreach ($this->masterFields() as $field) {
            MasterField::updateOrCreate(['key' => $field['key']], $field);
        }

        $format = BankFileFormat::updateOrCreate(
            ['name' => 'Bank Portal Default'],
            [
                'delimiter' => '|',
                'extension' => 'txt',
                'include_header' => false,
                'date_format' => 'Y-m-d',
                'decimal_places' => 2,
                'filename_pattern' => 'MENDubai{currency}{bank_account}{date}{sequence}',
                'trailing_delimiter' => true,
                'is_active' => true,
            ],
        );

        foreach ($this->bankColumns() as $column) {
            $format->columns()->updateOrCreate(['position' => $column['position']], $column);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function masterFields(): array
    {
        return [
            ['key' => 'transfer_type', 'label' => 'Transfer type', 'data_type' => 'text', 'is_required' => true, 'default_value' => 'TT', 'import_aliases' => ['transfer_type'], 'sort_order' => 10, 'is_active' => true],
            ['key' => 'beneficiary_bank_name', 'label' => 'Beneficiary bank name', 'data_type' => 'text', 'is_required' => true, 'import_aliases' => ['bank_name', 'beneficiary_bank'], 'sort_order' => 20, 'is_active' => true],
            ['key' => 'supplier_name', 'label' => 'Supplier name', 'data_type' => 'text', 'is_required' => true, 'import_aliases' => ['beneficiary_name', 'vendor_name', 'payee_name'], 'sort_order' => 30, 'is_active' => true],
            ['key' => 'amount', 'label' => 'Amount', 'data_type' => 'currency', 'is_required' => true, 'import_aliases' => ['payment_amount', 'transfer_amount'], 'sort_order' => 40, 'is_active' => true],
            ['key' => 'currency', 'label' => 'Currency', 'data_type' => 'text', 'is_required' => true, 'import_aliases' => ['payment_currency'], 'sort_order' => 50, 'is_active' => true],
            ['key' => 'beneficiary_bank_account', 'label' => 'IBAN / Account No.', 'data_type' => 'text', 'is_required' => true, 'import_aliases' => ['iban', 'account_number', 'beneficiary_account', 'iban_account_no'], 'sort_order' => 60, 'is_active' => true],
            ['key' => 'supplier_address', 'label' => 'Supplier address', 'data_type' => 'text', 'is_required' => true, 'import_aliases' => ['beneficiary_address', 'address'], 'sort_order' => 70, 'is_active' => true],
            ['key' => 'email', 'label' => 'Email', 'data_type' => 'text', 'is_required' => true, 'import_aliases' => ['emails', 'beneficiary_email'], 'sort_order' => 80, 'is_active' => true],
            ['key' => 'purpose_of_payment', 'label' => 'Purpose of payment', 'data_type' => 'text', 'is_required' => true, 'default_value' => 'Payment for goods or services for media broadcasting, production, events, etc.', 'import_aliases' => ['payment_purpose', 'remittance_details'], 'sort_order' => 90, 'is_active' => true],
            ['key' => 'beneficiary_bank_country', 'label' => 'Beneficiary bank country', 'data_type' => 'country', 'is_required' => true, 'import_aliases' => ['bank_country', 'country'], 'sort_order' => 100, 'is_active' => true],
            ['key' => 'bic_code', 'label' => 'BIC Code', 'data_type' => 'text', 'is_required' => true, 'import_aliases' => ['swift', 'swift_code', 'bic'], 'sort_order' => 110, 'is_active' => true],
            ['key' => 'us_routing_no', 'label' => 'US Routing No.', 'data_type' => 'text', 'is_required' => false, 'import_aliases' => ['aba', 'routing_no'], 'sort_order' => 120, 'is_active' => true],
            ['key' => 'uk_sort_code', 'label' => 'UK Sort code', 'data_type' => 'text', 'is_required' => false, 'import_aliases' => ['sort_code'], 'sort_order' => 130, 'is_active' => true],
            ['key' => 'future1', 'label' => 'Future1', 'data_type' => 'text', 'is_required' => false, 'import_aliases' => ['future_1'], 'sort_order' => 140, 'is_active' => true],
            ['key' => 'bank_charges', 'label' => 'Bank Charges', 'data_type' => 'text', 'is_required' => true, 'import_aliases' => ['bank_charges ', 'charges'], 'sort_order' => 150, 'is_active' => true],
            ['key' => 'purpose_code', 'label' => 'Purpose code', 'data_type' => 'reason_code', 'is_required' => true, 'default_value' => 'PRS', 'import_aliases' => ['reason_code'], 'sort_order' => 160, 'is_active' => true],
            ['key' => 'country_purpose_code', 'label' => 'Country purpose code', 'data_type' => 'reason_code', 'is_required' => false, 'import_aliases' => ['country_reason_code', 'country_purpose'], 'sort_order' => 170, 'is_active' => true],
            ['key' => 'future2', 'label' => 'Future 2', 'data_type' => 'text', 'is_required' => false, 'import_aliases' => ['future_2'], 'sort_order' => 180, 'is_active' => true],
            ['key' => 'payment_no', 'label' => 'Payment No.', 'data_type' => 'text', 'is_required' => true, 'import_aliases' => ['payment_number', 'payment_no'], 'sort_order' => 190, 'is_active' => true],
            ['key' => 'future3', 'label' => 'Future 3', 'data_type' => 'text', 'is_required' => false, 'import_aliases' => ['future_3'], 'sort_order' => 200, 'is_active' => true],
            ['key' => 'address_country', 'label' => 'Address / Country', 'data_type' => 'country', 'is_required' => true, 'import_aliases' => ['address_country', 'supplier_country'], 'sort_order' => 210, 'is_active' => true],
            ['key' => 'state', 'label' => 'State', 'data_type' => 'text', 'is_required' => true, 'import_aliases' => ['province'], 'sort_order' => 220, 'is_active' => true],
            ['key' => 'city', 'label' => 'City', 'data_type' => 'text', 'is_required' => true, 'import_aliases' => ['town'], 'sort_order' => 230, 'is_active' => true],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function countryReasonCodes(): array
    {
        return [
            ['country_code' => 'JORDAN', 'reason_code' => '0807', 'description' => 'Jordan country purpose code', 'is_active' => true],
            ['country_code' => 'KUWAIT', 'reason_code' => 'DUES', 'description' => 'Kuwait country purpose code', 'is_active' => true],
            ['country_code' => 'QATAR', 'reason_code' => 'A2E05', 'description' => 'Qatar country purpose code', 'is_active' => true],
            ['country_code' => 'CHINA', 'reason_code' => 'CSTRDR', 'description' => 'China country purpose code', 'is_active' => true],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function bankCountries(): array
    {
        $path = database_path('data/bank_countries.csv');
        $countries = [];

        if (! is_file($path)) {
            return $countries;
        }

        $file = new \SplFileObject($path);
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);
        $file->fgetcsv();

        while (! $file->eof()) {
            $row = $file->fgetcsv();
            if (! is_array($row) || $row === [null] || blank($row[0] ?? null)) {
                continue;
            }

            $countries[] = [
                'country_name' => trim((string) $row[0]),
                'capital' => trim((string) ($row[1] ?? '')),
            ];
        }

        return $countries;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bankColumns(): array
    {
        return [
            ['position' => 1, 'column_label' => 'Transfer type', 'source_field' => 'transfer_type', 'is_required' => true, 'is_active' => true],
            ['position' => 2, 'column_label' => 'Beneficiary bank name', 'source_field' => 'beneficiary_bank_name', 'is_required' => true, 'is_active' => true],
            ['position' => 3, 'column_label' => 'Supplier name', 'source_field' => 'supplier_name', 'is_required' => true, 'is_active' => true],
            ['position' => 4, 'column_label' => 'Amount', 'source_field' => 'amount', 'is_required' => true, 'is_active' => true],
            ['position' => 5, 'column_label' => 'Currency', 'source_field' => 'currency', 'is_required' => true, 'is_active' => true],
            ['position' => 6, 'column_label' => 'IBAN / Account No.', 'source_field' => 'beneficiary_bank_account', 'is_required' => true, 'is_active' => true],
            ['position' => 7, 'column_label' => 'Supplier address', 'source_field' => 'supplier_address', 'is_required' => true, 'is_active' => true],
            ['position' => 8, 'column_label' => 'Email', 'source_field' => 'email', 'is_required' => true, 'is_active' => true],
            ['position' => 9, 'column_label' => 'Purpose of payment', 'source_field' => 'purpose_of_payment', 'is_required' => true, 'is_active' => true],
            ['position' => 10, 'column_label' => 'Beneficiary bank country', 'source_field' => 'beneficiary_bank_country', 'is_required' => true, 'is_active' => true],
            ['position' => 11, 'column_label' => 'BIC Code', 'source_field' => 'bic_code', 'is_required' => true, 'is_active' => true],
            ['position' => 12, 'column_label' => 'US Routing No.', 'source_field' => 'us_routing_no', 'is_required' => false, 'is_active' => true],
            ['position' => 13, 'column_label' => 'UK Sort code', 'source_field' => 'uk_sort_code', 'is_required' => false, 'is_active' => true],
            ['position' => 14, 'column_label' => 'Future1', 'source_field' => 'future1', 'is_required' => false, 'is_active' => true],
            ['position' => 15, 'column_label' => 'Bank Charges', 'source_field' => 'bank_charges', 'is_required' => true, 'is_active' => true],
            ['position' => 16, 'column_label' => 'Purpose code', 'source_field' => 'purpose_code', 'is_required' => true, 'is_active' => true],
            ['position' => 17, 'column_label' => 'Country purpose code', 'source_field' => 'country_purpose_code', 'is_required' => false, 'is_active' => true],
            ['position' => 18, 'column_label' => 'Future 2', 'source_field' => 'future2', 'is_required' => false, 'is_active' => true],
            ['position' => 19, 'column_label' => 'Payment No.', 'source_field' => 'payment_no', 'is_required' => true, 'is_active' => true],
            ['position' => 20, 'column_label' => 'Future 3', 'source_field' => 'future3', 'is_required' => false, 'is_active' => true],
            ['position' => 21, 'column_label' => 'Address / Country', 'source_field' => 'address_country', 'is_required' => true, 'is_active' => true],
            ['position' => 22, 'column_label' => 'State', 'source_field' => 'state', 'is_required' => true, 'is_active' => true],
            ['position' => 23, 'column_label' => 'City', 'source_field' => 'city', 'is_required' => true, 'is_active' => true],
        ];
    }
}
