<?php

namespace Digitalis;

use Digitalis\Field;
use Digitalis\Component\Table;

abstract class CSV_Iterator extends Iterator {

    protected $file       = '';
    protected $upload     = false;
    protected $upload_dir = ABSPATH . '../temp/csv';
    protected $delimiter  = ',';
    protected $enclosure  = "\"";
    protected $escape     = '';
    protected $has_header = true;
    protected $headers    = [];

    protected $labels = [
        'single'    => 'row',
        'plural'    => 'rows',
    ];

    protected $mime_types = [
        'text/csv',
        'text/plain',
        'application/csv',
        'text/comma-separated-values',
        'application/excel',
        'application/vnd.ms-excel',
        'application/vnd.msexcel',
        'text/anytext',
        'application/octet-stream',
        'application/txt',
    ];

    protected $upload_errors = [
        UPLOAD_ERR_INI_SIZE   => 'The file is larger than the server allows (upload_max_filesize).',
        UPLOAD_ERR_FORM_SIZE  => 'The file is larger than the form allows.',
        UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded, please try again.',
        UPLOAD_ERR_NO_FILE    => 'Please select a .csv file to continue.',
        UPLOAD_ERR_NO_TMP_DIR => 'The server is missing a temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'The server failed to write the file to disk.',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload.',
    ];

    public function process_row ($row) {}

    //

    public function get_items () {

        if (!$handle = $this->open_csv()) {

            $this->error("Unable to open csv at '{$this->file}'.");
            return [];

        }

        // Resume from the byte offset where the last batch stopped rather than rescanning the file.
        $cursor = $this->store['cursor'] ?? [];
        $resume = ($cursor['index'] ?? null) === $this->index;
        $skip   = $resume ? 0 : $this->index;

        if ($resume) fseek($handle, $cursor['offset']);

        $rows = [];

        while (count($rows) < $this->batch_size && ($row = $this->read_row($handle)) !== false) {

            if ($skip-- > 0) continue;

            $rows[] = $this->key_row($row);

        }

        $this->store['cursor'] = [
            'index'  => $this->index + count($rows),
            'offset' => ftell($handle),
        ];

        fclose($handle);

        return $rows;

    }

    public function get_total_items () {

        // warm_up() also reloads the index, which would rewind a batch in progress.
        if (!$this->file) $this->warm_up();

        if (!$handle = $this->open_csv()) return 0;

        for ($total = 0; $this->read_row($handle) !== false; $total++);

        fclose($handle);

        return $total;

    }

    public function get_item_id ($item) {

        return $this->index + 1;

    }

    public function process_item ($item) {

        return $this->process_row($item);

    }

    //

    protected function open_csv () {

        if (!$this->file || !is_file($this->file)) return false;
        if (!$handle = fopen($this->file, 'r'))    return false;

        if (fread($handle, 3) !== "\xEF\xBB\xBF") rewind($handle);

        if ($this->has_header && ($row = $this->read_row($handle))) {

            $this->headers = array_map(
                fn($h) => strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $h), '_')),
                $row
            );

        }

        return $handle;

    }

    // Skips rows with no content: blank lines and spreadsheet padding like ",,,,".
    protected function read_row ($handle) {

        while (($row = fgetcsv($handle, 0, $this->delimiter, $this->enclosure, $this->escape)) !== false) {

            $row = array_map('strval', $row);

            if (trim(implode('', $row)) !== '') return $row;

        }

        return false;

    }

    protected function key_row ($row) {

        if (!$this->headers) return $row;

        $keyed_row = [];

        foreach ($row as $i => $cell) $keyed_row[$this->headers[$i] ?? $i] = $cell;

        return $keyed_row;

    }

    //

    public function __construct () {

        if (($_GET['page'] ?? 0) == $this->get_menu_slug()) add_action('admin_init', [$this, 'maybe_upload_csv']);

        parent::__construct();

    }

    protected function notice ($message, $type = 'error') {
    
        add_action('admin_notices', function () use ($message, $type){

            printf('<div class="%1$s"><p>%2$s</p></div>', "notice notice-{$type}", esc_html($message));

        });
    
    }

    public function maybe_upload_csv () {

        if (!$csv = ($_FILES['csv'] ?? 0)) return;
        if (!isset($csv['tmp_name']))      return;

        if (is_string($error = $this->validate_upload($csv)) || is_string($error = $this->validate_fields())) {

            $this->notice($error, 'error');
            return;

        }

        $this->upload_csv();
    
    }

    protected function validate_upload ($csv) {

        $path = $csv['tmp_name'];

        if (!current_user_can($this->capability))                return '🔒 You do not have permission to perform this action.';
        if (!$nonce = $_POST['nonce'] ?? 0)                      return '🤡 No funny business please.';
        if (!wp_verify_nonce($nonce, $this->key))                return '🕒 This page has expired, please refresh and try again.';
        if ($this->get_store()['file'] ?? 0)                     return '⏳ A file is already loaded, reset the import before uploading another.';
        if ($code = $csv['error'] ?? 0)                          return '📤 ' . ($this->upload_errors[$code] ?? "Upload failed with error code {$code}.");
        if (!filesize($path))                                    return '👻 The uploaded file is empty.';
        if (!in_array(mime_content_type($path), $this->mime_types)) return '📄 Please upload a valid .csv file.';

        return true;

    }

    protected function upload_csv () {

        $csv       = $_FILES['csv'];
        $file_path = trailingslashit($this->upload_dir) . $this->key . '.csv';

        if (!wp_mkdir_p($this->upload_dir)) {

            $this->notice("📁 Unable to create the upload directory '{$this->upload_dir}'.", 'error');
            return;

        }

        if (!move_uploaded_file($csv['tmp_name'], $file_path)) {

            $this->notice('📤 An unexpected error occured while uploading the file.', 'error');
            return;

        }

        chmod($file_path, 0600);

        $this->notice("✔️ Successfully uploaded '{$csv['name']}'.", 'updated');

        $this->get_store();
        $this->store['file'] = [
            'name' => $csv['name'],
            'path' => $file_path,
        ];
        $this->update_store();
    
    }

    public function warm_up () {

        parent::warm_up();

        if ($this->store['file']['path'] ?? 0) {

            $this->file = $this->store['file']['path'];

        }

    }

    public function get_fields () {

        return [
            new Field\Hidden([
                'name'   => 'nonce',
                'value'  => wp_create_nonce($this->key),
            ]),
            new Field\File([
                'name'   => 'csv',
                'label'  => 'Select CSV File',
                'accept' => '.csv',
            ]),
        ];

    }

    public function get_submit_field () {
    
        return new Field\Submit([
            'text' => 'Upload',
        ]);
    
    }

    public function validate_fields () {

        return true;
    
    }

    public function render_controller () {

        if (!$this->file) {

            echo "<style>" . file_get_contents(DIGITALIS_FRAMEWORK_PATH . '/assets/css/iterator.css') . "</style>";

            $fields   = $this->get_fields();
            $fields[] = $this->get_submit_field();

            Form::render([
                'classes' => ['iterator-panel', 'intake'],
                'action'  => $_SERVER['REQUEST_URI'],
                'method'  => 'post',
                'attributes' => [
                    'enctype' => 'multipart/form-data',
                ],
                'fields' => $fields,
            ]);

        } else {

            echo "<div class='iterator-panel csv-info'>";
            Table::render([
                'rows'      => $this->get_csv_info_rows(),
                'first_row' => false,
                'first_col' => true,
            ]);
            echo "</div>";

            parent::render_controller();

        }

    }

    protected function get_csv_info_rows () {
    
        $rows = [];
        if ($file_name = $this->store['file']['name'] ?? 0) $rows[] = ['File:', $file_name];
        if ($total     = $this->get_total_items_wrap())     $rows[] = ['Rows:', $total];
        return $rows;
    
    }

    public function reset () {

        // Uploads can hold personal data, so they don't outlive the import.
        if (($path = $this->get_store()['file']['path'] ?? '') && is_file($path)) unlink($path);

        $response = parent::reset();
        $response['reload'] = true;
        return $response;

    }

}
