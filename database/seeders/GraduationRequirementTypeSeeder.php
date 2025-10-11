<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GraduationRequirementType;

class GraduationRequirementTypeSeeder extends Seeder {
    public function run(): void {
        $items = [
            ['code'=>'FORM',          'name'=>'Application for Graduation Form (original)', 'sort'=>1],
            ['code'=>'ACCTG_CLR',     'name'=>'Accounting Office Clearance',               'sort'=>2],
            ['code'=>'REG_CLR',       'name'=>'Registrar Clearance',                       'sort'=>3],
            ['code'=>'LIB_CLR',       'name'=>'Library Clearance',                         'sort'=>4],
            ['code'=>'THESIS_HARDB',  'name'=>'Clear Copy / Submission of Hardbound Thesis','sort'=>5],
        ];

        foreach ($items as $i) {
            GraduationRequirementType::updateOrCreate(
                ['code'=>$i['code']],
                ['name'=>$i['name'], 'is_file_required'=>true, 'sort'=>$i['sort']]
            );
        }
    }
}
