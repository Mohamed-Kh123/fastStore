<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use Intervention\Image\Facades\Image;

class ImageController extends Controller
{
    public function getImage($folder, $id)
    {
        $path = storage_path("app/public/$folder/$id");

        $exists = File::exists($path);
        if (!$exists) {
            return asset('storage/null.jpg');
        }

        $image = Image::make($path);
        $response = Response::make($image->encode($image->mime), 200);
        $response->header("CF-Cache-Status", 'HIF');
        $response->header("Cache-Control", 'max-age=604800, public');
        $response->header("Content-Type", $image->mime);
        // Generate a URL for the image
        return $response;
    }
}
