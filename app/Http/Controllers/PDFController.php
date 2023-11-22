<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PDFController extends Controller
{
    public function merge(Request $request)
    {
        //check api key
        if ($request->api_key != env('API_KEY')) {
            return response()->json([
                'message' => 'Invalid API Key'
            ], 401);
        }

        //store request json to laravel log
        \Log::info($request->json()->all());

        //read json
        $json = $request->json()->all();
        $pdf_links = ($json['files']);

        //download pdfs and save to local storage folder temp and convert to version 1.4
        $pdf_files = [];
        $temp_files = [];
        $i = 0;
        foreach ($pdf_links as $pdf_link) {

            $pdf_file = file_get_contents($pdf_link);
            // random file name encrypted with original extension
            $pdf_file_name = md5($pdf_link . time() . $i) . '.' . pathinfo($pdf_link, PATHINFO_EXTENSION);
            $pdf_file_path = 'temp/' . $pdf_file_name;
            Storage::put($pdf_file_path, $pdf_file);
            $pdf_file_path = storage_path('app/' . $pdf_file_path);
            array_push($temp_files, $pdf_file_path);
            $i++;
            // if not pdf dont convert
            $converted_pdf_name = $pdf_file_name . '_converted.pdf';
            $converted_pdf_path = storage_path('app/temp/' . $converted_pdf_name);
            if (!preg_match('/\.pdf$/i', $pdf_file_path)) {
               // convert to pdf using imagemagick
                shell_exec("convert " . $pdf_file_path . " " . $converted_pdf_path);
                array_push($pdf_files, $converted_pdf_path);
                continue;
            }
            // convert to version 1.4 using ghostscript pdf and images to pdf

            shell_exec("gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -sOutputFile=" . $converted_pdf_path . " -dCompatibilityLevel=1.4 " . $pdf_file_path);
            array_push($pdf_files, $converted_pdf_path);
        }

        // store pdf files in local storage on outputs folder
        $output_path_name = storage_path('app/outputs/') . md5(time()) . '.pdf';

        //merge using ghostscript
        $gs_command = "gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -sOutputFile=" . $output_path_name . " ";
        foreach ($pdf_files as $pdf_file) {
            $gs_command .= $pdf_file . " ";
        }

        shell_exec($gs_command);

        //convert merged pdf to base64 string
        $output_file = file_get_contents($output_path_name);

        if($request->has('return_type')){
            if($request->return_type == 'link'){
                // return url to output path name
                //move file to public folder
                $output_file_name = md5(time()) . '.pdf';
                $output_file_path = 'public/pdf/' . $output_file_name;
                Storage::put($output_file_path, $output_file);
                $output_file_path = storage_path('app/' . $output_file_path);
                // delete files and converted pdfs
                foreach ($temp_files as $temp_file) {
                    unlink($temp_file);
                }
                foreach ($pdf_files as $pdf_file) {
                    unlink($pdf_file);
                }
                unlink($output_path_name);

                return response()->json([
                    'message' => 'success',
                    'data' => [
                        'file' => url('/storage/pdf/' . $output_file_name)
                    ]
                ], 200);

            }
        }

        $output_file_base64 = base64_encode($output_file);

        // delete files and converted pdfs
        foreach ($temp_files as $temp_file) {
            unlink($temp_file);
        }
        foreach ($pdf_files as $pdf_file) {
            unlink($pdf_file);
        }
        unlink($output_path_name);

        return response()->json([
            'message' => 'success',
            'data' => [
                'file' => $output_file_base64
            ]
        ], 200);
    }
}
