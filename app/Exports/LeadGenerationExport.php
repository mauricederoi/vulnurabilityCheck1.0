<?php

namespace App\Exports;

use App\Models\LeadGeneration;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;

class LeadGenerationExport implements FromCollection,WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */
	
	public function headings():array{
        return[
            'Id',
            'Name',
            'Email',
            'City',
            'Date',
            'Updated At' 
        ];
    }
    public function collection()
    {
        return LeadGeneration::all();
    }
}
