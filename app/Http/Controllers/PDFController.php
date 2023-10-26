<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PDFController extends Controller
{
    public function merge(Request $request){
        // {
        //     "api_key": 1234567890,
        //     "files": [
        //         "https://www.africau.edu/images/default/sample.pdf",
        //         "https://www.africau.edu/images/default/sample.pdf",
        //         "https://www.africau.edu/images/default/sample.pdf"
        //     ]
        // }
        //check api key
        if($request->api_key != env('API_KEY')){
            return response()->json([
                'message' => 'Invalid API Key'
            ], 401);
        }

        //read json
        $json = $request->json()->all();
        $pdf_links = ($json['files']);

        //download pdfs and save to local storage folder temp and convert to version 1.4
        $pdf_files = [];
        $i=0;
        foreach($pdf_links as $pdf_link){
            $pdf_file = file_get_contents($pdf_link);
            // random file name encrypted
            $pdf_file_name = md5($pdf_link . time() . $i) . '.pdf';
            $pdf_file_path = 'temp/' . $pdf_file_name;
            $store = Storage::put($pdf_file_path, $pdf_file);
            $pdf_file_path = storage_path('app/' . $pdf_file_path);
            $converted_pdf_name = $pdf_file_name . '_converted.pdf';
            $converted_pdf_path = storage_path('app/temp/' . $converted_pdf_name);
            shell_exec("gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -sOutputFile=". $converted_pdf_path . " -dCompatibilityLevel=1.4 " . $pdf_file_path);
            array_push($pdf_files, $converted_pdf_path);
            $i++;
        }

        // store pdf files in local storage on outputs folder
        $output_path_name = storage_path('app/outputs/'). md5(time()) . '.pdf';

        //merge using ghostscript
        $gs_command = "gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -sOutputFile=". $output_path_name . " ";
        foreach($pdf_files as $pdf_file){
            $gs_command .= $pdf_file . " ";
        }
        // dd($gs_command);
        shell_exec($gs_command);

        //convert merged pdf to base64 string
        $output_file = file_get_contents($output_path_name);
        $output_file_base64 = base64_encode($output_file);

        //delete temp files
        foreach($pdf_files as $pdf_file){
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
