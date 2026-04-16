<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class FileStorage
{
    /**
     * Store photo and protect the site
     *
     * @param  mixed  $file The uploaded file
     * @param  string  $folderName The folder to upload the file to
     * @param  string  $suffix The file type suffix (img, vid, aud, docs)
     * @return string|null The url of the stored file
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    public static function storeFile($file, string $folderName, $suffix)
    {
        try {
            // إلغاء جميع القيود: لا تحقق من الامتداد أو النوع أو الحجم
            $extension = strtolower($file->getClientOriginalExtension());
            $fileName = Str::random(32);
            $fileName = preg_replace('/[^A-Za-z0-9_\-]/', '', $fileName);
            $expectedFileName = $fileName . '.' . $extension;
            $path = $file->storeAs($folderName, $expectedFileName, 'public');

            if (!$path || !Storage::disk('public')->exists($path)) {
                self::throwValidationError('file', 'حدث خطأ أثناء حفظ الملف');
            }

            return Storage::url($path);
        } catch (\Exception $e) {
            self::throwValidationError('file', 'حدث خطأ أثناء معالجة الملف');
        }
    }

    /**
     * Throw validation error in JSON format
     *
     * @param string $field
     * @param string $message
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected static function throwValidationError($field, $message)
    {
        $validator = Validator::make([], []);
        $validator->errors()->add($field, $message);

        throw new HttpResponseException(response()->json([
            'status' => 'error',
            'message' => 'حدث خطأ في التحقق من صحة البيانات',
            'errors' => $validator->errors()
        ], 422));
    }

    /**
     * Check if a file exists and upload it.
     *
     * This method checks if a file exists in the request and uploads it to the specified folder.
     * If the file doesn't exist, it returns null.
     *
     * @param  Request  $request The HTTP request object.
     * @param  string  $folder The folder to upload the file to.
     * @param  string  $fileColumnName The name of the file input field in the request.
     * @return string|null The file path if the file exists, otherwise null.
     */
    public static function fileExists($file, $old_file, string $folderName, $suffix)
    {
        if (!isset($file)) {
            return null;
        }
        self::deleteFile($old_file);
        return self::storeFile($file, $folderName, $suffix);
    }

    /**
     * Delete the specified file.
     *
     * This method takes a file path as input and deletes the corresponding file from the public directory.
     * It first checks if the file exists at the given file path, and if it does, it deletes the file using the `unlink()` function.
     *
     * @param string $file The file path of the file to be deleted.
     * @return void
     */
    public static function deleteFile($file)
    {
        $filePath = public_path($file);
        if (file_exists($filePath)) {
            Storage::disk('public')->delete($filePath);
        }
    }
}
