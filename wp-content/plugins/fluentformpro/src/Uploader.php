<?php

namespace FluentFormPro;

use FluentForm\App\Helpers\Helper;
use FluentForm\App\Modules\Form\FormHandler;
use FluentForm\App\Modules\Form\FormFieldsParser;
use FluentForm\Framework\Helpers\ArrayHelper;
use FluentValidator\Arr;

class Uploader extends FormHandler
{
    /**
     * Uploads files to the server.
     */
    public function upload()
    {
        $formId = intval($this->request->get('formId'));
        
        $this->formData = $this->request->all();
        
        if ($formId) {
            $this->setForm($formId);

            $this->validateNonce();

            if ($this->form) {
                // Get the HTTP files. It'll be an array with always one item.
                $files = $this->request->files();

                do_action('fluentform_starting_file_upload', $files, $this->form);

                // Get the form attribute name then.
                $arrayKeys = array_keys($files);
                $attribute = array_pop($arrayKeys);

                // Get the specific form field by using the element type and it's attribute name.
                $field = FormFieldsParser::getField(
                    $this->form,
                    ['input_file', 'input_image', 'featured_image'],
                    $attribute,
                    ['rules', 'settings']
                );

                if ($field) {
                    // Extract the validation rules & messages for file upload element.
                    list($rules, $messages) = FormFieldsParser::getValidations(
                        $this->form,
                        $files,
                        $field
                    );
                    /**
                     * Delegate 'max_file_size', 'allowed_file_types' rules & messages to
                     * 'max', 'mimes' since the validation library doesn't recognise those
                     */
                    list($rules, $messages) = $this->delegateValidations($rules, $messages);
                    
                    // Fire an event so that one can hook into it to work with the rules & messages.
                    $validations = $this->app->applyFilters(
                        'fluentform_file_upload_validations',
                        [$rules, $messages],
                        $this->form
                    );
                    
                    $validator = \FluentValidator\Validator::make(
                        $files,
                        $validations[0],
                        $validations[1]
                    );
                    
                    if ($validator->validate()->fails()) {
                        // Fire an event so that one can hook into it to work with the errors.
                        $errors = $this->app->applyFilters(
                            'fluentform_file_upload_validation_error',
                            $validator->errors(),
                            $this->form
                        );
                        
                        wp_send_json([
                            'errors' => $errors
                        ], 422);
                    }
    
                    // let's upload to a temp location
                    $field = current($field);
    
                    //add default upload location for old inputs
                    if (!$uploadLocation = ArrayHelper::get($field, 'settings.upload_file_location')) {
                        $uploadLocation = 'default';
                    }

                    $uploadedFiles = [];
                    if (!empty($uploadLocation)) {
                        $this->overrideUploadDir();
                        $uploadedFiles = $this->uploadToTemp($files, $field);
                    }
    
                    wp_send_json_success([
                        'files' => $uploadedFiles
                    ], 200);
                }
            }
        }
    }
    
    /**
     * Uploads files to its target locations
     * @return void
     */
    public function processFiles()
    {
        $fileTypes = ['input_file', 'input_image', 'featured_image'];
        
        foreach ($fileTypes as $fileType) {
            add_filter('fluentform_input_data_' . $fileType, function ($files, $field, $formData, $form) {
                $uploadLocation = $this->getUploadLocation($field);
                $files = is_array($files) ? $files : [$files];
                $files = $this->maybeDecrypt($files);
                
                do_action('fluentform_starting_file_processing', $files, $uploadLocation, $formData, $form);
                
                $this->initUploads($files, $uploadLocation);
                
                $formattedFiles = [];
                foreach ($files as $file) {
                    $formattedFiles[] = $this->getProcessedUrl($file, $uploadLocation);
                }
                return $formattedFiles;
            }, 10, 4);
        }
    }
    
    /**
     * Register filters for custom upload dir
     */
    public function overrideUploadDir()
    {
        add_filter('wp_handle_upload_prefilter', function ($file) {
            add_filter('upload_dir', [$this, 'setCustomUploadDir']);

            add_filter('wp_handle_upload', function ($fileinfo) {
                remove_filter('upload_dir', [$this, 'setCustomUploadDir']);
                $fileinfo['file'] = basename($fileinfo['file']);
                return $fileinfo;
            });
            
            return $this->renameFileName($file);
        });
    }
    
    /**
     * Set plugin's custom upload dir
     * @param array $param
     * @return array $param
     */
    public function setCustomUploadDir($param)
    {
        $param['url'] = $param['baseurl'] . FLUENTFORM_UPLOAD_DIR . '/temp';
        $param['path'] = $param['basedir'] . FLUENTFORM_UPLOAD_DIR . '/temp';
        
        $param = apply_filters('fluentform_file_upload_params', $param, $this->formData, $this->form);
        
        $this->secureDirectory($param['path']);
        
        return $param;
    }
    
    /**
     * Rename the uploaded file name before saving
     * @param array $file
     * @return array $file
     */
    public function renameFileName($file)
    {
        $originalFileArray = $file;
        $prefix = 'ff-' . md5(uniqid(rand())) . '-ff-';
        
        $file['name'] = $prefix . $file['name'];
        
        return apply_filters('fluentform_uploaded_file_name', $file, $originalFileArray, $this->formData, $this->form);
    }
    
    /**
     * Prepare the validation rules & messages specific to
     * file type inputs when actual form is submitted.
     *
     * @param $validations
     * @param $form \stdClass
     * @return array
     */
    public function prepareValidations($validations, $form)
    {
        $element = FormFieldsParser::getElement($form, ['input_file', 'input_image']);
        
        if (count($element)) {
            // Delegate the `max_file_count` validation to `max`
            $validations = $this->delegateValidations(
                $validations[0],
                $validations[1],
                ['max_file_count'],
                ['max']
            );
        }
        
        return $validations;
    }
    
    /**
     * Process uploads from temp directory location to its final location
     *
     * @param $formData
     * @param $form
     * @return void
     */
    public function initUploads($files, $uploadLocation)
    {
        if (empty($files)) {
            return;
        }
        if ($uploadLocation == 'wp_media') {
            $this->copyToWpMedia($files);
        } elseif ($uploadLocation == 'default') {
            $this->copyToDefault($files);
        }
        self::removeOldTempFiles();
    }
    
    /**
     * Copy files to default location
     *
     * @param array $files
     */
    protected function copyToDefault($files)
    {
        $uploadedFiles = [];
        $wpUploadDir = wp_upload_dir();
        
        foreach ($files as $file) {
            $fileInfo = pathinfo($file);
            $filename = $fileInfo['basename'];
            $tempFilePath = $wpUploadDir['basedir'] . FLUENTFORM_UPLOAD_DIR . '/temp/' . $filename;
            $destinationFilePath = $wpUploadDir['basedir'] . FLUENTFORM_UPLOAD_DIR . '/' . $filename;
            $this->secureDirectory($wpUploadDir['basedir'] . FLUENTFORM_UPLOAD_DIR);
            self::copyFile($tempFilePath, $destinationFilePath);
        }
        return $uploadedFiles;
    }
    
    
    /**
     * Copy files to WordPress Media
     *
     * @param $files
     * @return void
     */
    public function copyToWpMedia($files)
    {
        $uploadedFiles = [];
        $wpUploadDir = wp_upload_dir();
    
        foreach ($files as $file) {
            $fileInfo = pathinfo($file);
            $filename = $fileInfo['basename'];
            $tempFilePath = $wpUploadDir['basedir'] . FLUENTFORM_UPLOAD_DIR . '/temp/' . $filename;
            $destinationFilePath = $wpUploadDir['path'] . '/' . $filename;
        
            $mimeType = wp_check_filetype($tempFilePath);
            //Copy this file into the wp uploads dir
            $move = self::copyFile($tempFilePath, $destinationFilePath);
            if (!$move) {
                continue;
            }
            $destinationFileFileUrl = $wpUploadDir['url'] . '/' . $filename;
            $uploadId = wp_insert_attachment(
                [
                    'guid' => $destinationFileFileUrl,
                    'post_mime_type' => $mimeType['type'],
                    'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
                    'post_content' => '',
                    'post_status' => 'inherit',
                ],
                $destinationFilePath
            );
        
            // wp_generate_attachment_metadata() needs this file.
            require_once ABSPATH . 'wp-admin/includes/image.php';
            if (!is_wp_error($uploadId)) {
                wp_update_attachment_metadata(
                    $uploadId,
                    wp_generate_attachment_metadata($uploadId, $destinationFilePath)
                );
            }
        }
    }
    
    /**
     * Upload files to temp directory
     *
     * @param array $files
     * @param array $field
     * @return array
     */
    private function uploadToTemp($files, $field)
    {
        $uploadedFiles = [];
        foreach ($files as $file) {
            /**
             * @var $file \FluentForm\Framework\Request\File
             */
            $filesArray = $file->toArray();
            
            $uploaderArgs = apply_filters('fluentform_uploader_args', [
                'test_form' => false
            ], $filesArray, $this->form);
            
            $uploadFile = wp_handle_upload(
                $filesArray,
                $uploaderArgs
            );
            
            $file = $uploadFile['file'];
            $uploadFile['file'] = \FluentForm\App\Helpers\Protector::encrypt($file);
            $uploadFile['url'] = str_replace($file, $uploadFile['file'], $uploadFile['url']);
            
            $uploadedFiles[] = apply_filters('fluent_file_uploaded', $uploadFile, $this->formData, $this->form);
        }
        return $uploadedFiles;
    }
    
    private static function copyFile($fromPath = null, $toPath = null)
    {
        $status = false;
        
        if (isset($fromPath) and file_exists($fromPath)) {
            //if destination dir exists if not make it
            if (!file_exists(dirname($toPath))) {
                mkdir(dirname($toPath));
            }
            if (file_exists(dirname($toPath))) {
                //Move file into dir
                if (copy($fromPath, $toPath)) {
                    if (file_exists($toPath)) {
                        $status = true;
                    }
                }
            }
        }
        
        return $status;
    }
    
    
    /**
     * Get File url after processing uploads
     *
     * @param $file
     * @param $uploadLocations
     * @return string|void
     */
    public function getProcessedUrl($file, $location)
    {
        $wpUploadDir = wp_upload_dir();
        $fileInfo = pathinfo($file);
        $filename = $fileInfo['basename'];
        $fileUrl = '';
        $filePath = '';
        if ($location == 'wp_media') {
            $filePath = $wpUploadDir['path'] . '/' . $filename;
            $fileUrl = $wpUploadDir['url'] . '/' . $filename;
        } elseif ($location == 'default') {
            $filePath = $wpUploadDir['basedir'] . FLUENTFORM_UPLOAD_DIR . '/' . $filename;
            $fileUrl = $wpUploadDir['baseurl'] . FLUENTFORM_UPLOAD_DIR . '/' . $filename;
        } else {
            //if not location found store temp file url for other file uploader
            $filePath = $wpUploadDir['basedir'] . FLUENTFORM_UPLOAD_DIR . '/temp/' . $filename;
            $fileUrl = $wpUploadDir['baseurl'] . FLUENTFORM_UPLOAD_DIR . '/temp/' . $filename;
        }
        return $fileUrl;
    }
    
    /**
     * Cleanup temp Directory
     *
     * @param $files
     * @return void
     */
    private static function removeOldTempFiles()
    {
        $maxFileAge = apply_filters('fluentforrm_temp_file_delete_time', 2 * 3600);
        $wpUploadDir = wp_upload_dir();
        $tempDir = $wpUploadDir['basedir'] . FLUENTFORM_UPLOAD_DIR . '/temp/';

        // Remove old temp files
        if (is_dir($tempDir) and ($dir = opendir($tempDir))) {
            while (($file = readdir($dir)) !== false) {
                $tempFilePath = $tempDir . DIRECTORY_SEPARATOR . $file;
                if ((filemtime($tempFilePath) < time() - $maxFileAge)) {
                    @unlink($tempFilePath);
                }
            }
            closedir($dir);
        }
    }
    
    /**
     * Adds htaccess file to directory
     * @param $path
     * @return void
     */
    private function secureDirectory($path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755);
            file_put_contents(
                $path . '/.htaccess',
                file_get_contents(__DIR__ . '/Stubs/htaccess.stub')
            );
        }
    }
    
    
    /**
     * Maybe Decrypt file names
     *
     * @param $files
     * @return array
     */
    private function maybeDecrypt($files)
    {
        $decrypted = [];
        foreach ($files as $file) {
            $uploadDir = str_replace('/', '\/', FLUENTFORM_UPLOAD_DIR . '/temp');
            
            $pattern = "/(?<=${uploadDir}\/).*$/";
            
            preg_match($pattern, $file, $match);
            
            if (!empty($match)) {
                $file = str_replace($match[0], \FluentForm\App\Helpers\Protector::decrypt($match[0]), $file);
            }
            $decrypted[] = $file;
        }
        return $decrypted;
    }
    
    public function deleteFile()
    {
        if (!empty($file_name = $this->request->get('path'))) {
            if (!empty($this->request->get('attachment_id')) && wp_delete_attachment(
                $this->request->get('attachment_id')
            )) {
                wp_die();
            } else {
                $file_name = \FluentForm\App\Helpers\Protector::decrypt($file_name);
                wp_die(@unlink(wp_upload_dir()['basedir'] . FLUENTFORM_UPLOAD_DIR . '/temp/' . $file_name));
            }
        }
    }
    
    /**
     * @param $upload_file_location
     * @return mixed
     */
    public function getUploadLocation($field)
    {
        if (!$locationType = ArrayHelper::get($field, 'raw.settings.file_location_type')) {
            $locationType = 'follow_global_settings';
        }
        if ($locationType == 'follow_global_settings') {
            $settings = get_option('_fluentform_global_form_settings', false);
            $location = ArrayHelper::get($settings, 'misc.file_upload_locations');
        } else {
            $location = ArrayHelper::get($field, 'raw.settings.upload_file_location');
        }
    
        if (empty($location)) {
            $location = 'default';
        }
        return $location;
    }
}
