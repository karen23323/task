<?php

namespace App\Console\Commands;

use App\City;
use App\HashedCity;
use Illuminate\Console\Command;

class CheckCitiesData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:city';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and update the cities';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        echo 'CheckCity is running';
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '59');
        $zip_file_name = 'RU.zip';
        $url = 'http://download.geonames.org/export/dump/RU.zip';
        $file = file_get_contents($url);
        file_put_contents(storage_path($zip_file_name), $file);
        $zip_file_path = storage_path($zip_file_name);

        //open zip file and extract to folder
        $zip = new \ZipArchive;
        $res = $zip->open($zip_file_path);
        if ($res === true) {
            $zip->extractTo(storage_path('RU'));
            $zip->close();
            \File::delete($zip_file_path);
        } else {
            echo 'failed, code:' . $res;
        }

        $cities_file = storage_path('RU\RU.txt');

        //get .txt file data
        $lines = file($cities_file, FILE_IGNORE_NEW_LINES);
        $data = file_get_contents($cities_file);

        //hashed cities data and check updated or no
        $data_hash = hash('sha256',$data);
        $hash = HashedCity::first();

        if (! empty($hash)) {
            if ($hash['hash'] == $data_hash) {
                return;
            } else {
                $hash->update(['hash' => $data_hash]);
                $this->updateCitiesTable($lines);
            }
        } else {
            $hash = new HashedCity;
            $hash->hash = $data_hash;
            $hash->save();
            $this->updateCitiesTable($lines);
        }
    }

    /**
     * @param $lines
     * Check and update Cities table
     */
    public function updateCitiesTable($lines)
    {
        //Cities table columns
        $columns = [
            'geonameid',
            'name',
            'asciiname',
            'alternatenames',
            'latitude',
            'longitude',
            'feature_class',
            'feature_code',
            'country_code',
            'cc2',
            'admin1_code',
            'admin2_code',
            'admin3_code',
            'admin4_code',
            'population',
            'elevation',
            'dem',
            'timezone',
            'modification_date'
        ];

        $cities = City::pluck('modification_date', 'geonameid')->toArray();
        $count_cities = count($cities);
        $date = City::max('modification_date');
        $item_geonameid = [];


        foreach ($lines as $line) {
            $values = preg_split('[\t]', $line);
            // get each city data
            $item = array_combine($columns, $values);
            // get items geonameid
            $items_geonameid[] = $item['geonameid'];

            if (!$date || $item['modification_date'] > $date) {
                $check = City::where('geonameid', $item['geonameid'])->first();
                if ($check) {
                    $check->timestamps = false;
                    $check->update(array_filter($item));
                } else {
                    City::insert(array_filter($item));
                }
            }
        }

        //delete city from Cities, which is absent from file
        if ($count_cities != 0 && count($item_geonameid) != 0) {
            foreach ($cities as $key => $val) {
                if (!in_array($key, $item_geonameid)) {
                    City::where('geonameid', $key)->delete();
                }
            }
        }
    }
}
