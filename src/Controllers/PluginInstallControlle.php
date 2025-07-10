<?php
namespace Abdurrahaman\Installer\Controllers;
use File;
use Exception;
use ZipArchive;
use Illuminate\Http\Request;
use Modules\Plugin\Models\Plugin;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class PluginInstallControlle extends Controller{

    public function install(Request $request)
    {
        $request->validate([
            "plugin" => 'required|mimes:zip'
        ]);

        try{
            $file = $request->file('plugin');

            // Save the uploaded ZIP file
            $zipPath = $file->storeAs('temp', $file->getClientOriginalName());
            $zipFullPath = storage_path('app/' . $zipPath);

            // Prepare extraction folder
            $extractPath = storage_path('app/temp/extracted');
            File::deleteDirectory($extractPath);
            File::makeDirectory($extractPath);

            $zip = new ZipArchive;
            if ($zip->open($zipFullPath) === TRUE) {
                $zip->extractTo($extractPath);
                $zip->close();
            } else {
                File::delete($zipFullPath); // clean up ZIP file
                Toastr::error("Extract Failed");
                return back();

            }
            // Find extracted folder
            $folders = File::directories($extractPath);
            if (count($folders) === 0) {
                File::delete($zipFullPath);
                File::deleteDirectory($extractPath);
                Toastr::error("Folder not found");
                return back();
            }


            $folderPath = $folders[0];
            $folderName = basename($folderPath);
            $jsonFilePath = $folderPath . '/' . $folderName . '.json';


            if (!File::exists($jsonFilePath)) {

                File::delete($zipFullPath);
                File::deleteDirectory($extractPath);
                Toastr::error("Invalid Plugin");
                return back();
            }

            $destinationPath = base_path('Modules/' . $folderName);
            if (File::exists($destinationPath)) {
                File::deleteDirectory($destinationPath); // optional: overwrite
            }
            File::copyDirectory($folderPath, $destinationPath);
            File::delete($zipFullPath);
            File::deleteDirectory($extractPath);

            $module_json = file_get_contents(base_path('modules_statuses.json'));
            $module_list = (array) json_decode($module_json);
            $module_list[$folderName] = false;
            file_put_contents(base_path('modules_statuses.json'),json_encode($module_list,JSON_PRETTY_PRINT));


            //insert into DB
            $plugin_json = (array) json_decode(file_get_contents($destinationPath.'/'.$folderName.'.json'));
            $hasOld = Plugin::where('name',$plugin_json['name'])->first();

            if($hasOld){
                 $hasOld->update([
                    "version" => $plugin_json['version']
                 ]);
                if(isset($plugin_json['migrations']) && !empty($plugin_json['migrations']))
                {
                    foreach($plugin_json['migrations'] as $path)
                    {
                        Artisan::call('migrate', [
                            '--force' => true,
                            '--path' => $path
                        ]);
                    }
                }

            }else{
                Plugin::create([
                    "name" => $plugin_json['name'],
                    "purchase_code" => null,
                    "version" => $plugin_json['version'],
                    "is_active" => 0,
                    "description" =>  $plugin_json['description'],
                ]);
            }
            Toastr::success("Success");
            return back();
        }catch(Exception $e){
            dd($e);
            Toastr::error("Success");
            return back();
        }

    }

}
