<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PDFController extends Controller
{
    public function merge(Request $request)
    {
        // {
        //     "api_key": 1234567890,
        //     "files": [
        //         "https://www.africau.edu/images/default/sample.pdf",
        //         "https://www.africau.edu/images/default/sample.pdf",
        //         "https://www.africau.edu/images/default/sample.pdf"
        //     ]
        // }
        //check api key
        if ($request->api_key != env('API_KEY')) {
            return response()->json([
                'message' => 'Invalid API Key'
            ], 401);
        }

        //read json
        $json = $request->json()->all();
        $pdf_links = ($json['files']);

        //download pdfs and save to local storage folder temp and convert to version 1.4
        $pdf_files = [];
        $i = 0;
        foreach ($pdf_links as $pdf_link) {

            $pdf_file = file_get_contents($pdf_link);
            // random file name encrypted with original extension
            $pdf_file_name = md5($pdf_link . time() . $i) . '.' . pathinfo($pdf_link, PATHINFO_EXTENSION);
            $pdf_file_path = 'temp/' . $pdf_file_name;
            Storage::put($pdf_file_path, $pdf_file);
            $pdf_file_path = storage_path('app/' . $pdf_file_path);
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
        $output_file_base64 = base64_encode($output_file);

        // delete all  files in temp and output folder
        $files = Storage::files('temp');
        Storage::delete($files);
        $files = Storage::files('outputs');
        Storage::delete($files);

        return response()->json([
            'message' => 'success',
            'data' => [
                'file' => $output_file_base64
            ]
        ], 200);
    }
}
