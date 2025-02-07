<?php

namespace Digitalis;

use Digitalis\Field;
use Digitalis\Element\Table;

abstract class CSV_Iterator extends Iterator {

    protected $file       = '';
    protected $upload     = false;
    protected $upload_dir = ABSPATH . '../temp/csv';
    protected $delimiter  = ',';
    protected $enclosure  = "\"";
    protected $escape     = "\\";
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

    public function process_row ($row) {}

    //

    public function get_items () {

        if (!file_exists($this->file)) {

            $this->error("Unable to locate csv at '{$this->file}'.");
            return [];

        }

        if (($handle = fopen($this->file, "r")) === false) return [];

        $rows = [];

        $index_start = $this->index + ($this->has_header ? 1 : 0);
        $index_end =   $index_start + $this->batch_size;

        for ($i = 0; $row = fgetcsv($handle, 0, $this->delimiter, $this->enclosure, $this->escape); $i++) {

            $row = array_map(fn($value) => trim($value, "\xEF\xBB\xBF"), $row); // Remove BOM characters and such

            if (($i == 0) && $this->has_header) {

                $this->headers = $row;
                continue;

            }

            if ($i < $index_start) continue;
            if ($i >= $index_end)  break;

            if ($this->headers && $row) {

                $keyed_row = [];

                foreach ($row as $j => $cell) $keyed_row[$this->headers[$j] ?? $j] = $cell;

                $row = $keyed_row;

            }

            $rows[] = $row;

        }

        fclose($handle);

        return $rows;

    }

    public function get_total_items () {

        $this->warm_up();

        if (!file_exists($this->file)) return 0;

        if (($handle = fopen($this->file, "r")) === false) return 0;

        $i = 0;

        if ($this->has_header) $i--;

        while (($data = fgetcsv($handle, 0, $this->delimiter, $this->enclosure, $this->escape)) !== false) $i++;

        fclose($handle);

        return $i;

    }

    public function get_item_id ($item) {

        return $this->index + 1;

    }

    public function process_item ($item) {

        return $this->process_row($item);

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
    
        if (($csv = ($_FILES['csv'] ?? 0)) && $csv['tmp_name']) {

            if ($csv['error'] ?? 0) {

                $this->notice("📤 The following error occured while uploading: {$csv['error']}", 'error');
                return;

            }

            $error = false;

            $temp_path = $csv['tmp_name'];
            $file_size = filesize($temp_path);
            $file_info = finfo_open(FILEINFO_MIME_TYPE);
            $file_type = finfo_file($file_info, $temp_path);

            if (!current_user_can($this->capability))     $error = '🔒 You do not have permission to perform this action.';
            if (!$nonce = $_POST['nonce'] ?? 0)           $error = '🤡 No funny business please.';
            if (!wp_verify_nonce($nonce, $this->key))     $error = '🕒 This page has expired, please refresh and try again.';
            if (!$file_size)                              $error = '👻 The uploaded file is empty.';
            if (!in_array($file_type, $this->mime_types)) $error = '📄 Please upload a valid .csv file.';
 
            if ($error) {

                $this->notice($error, 'error');
                return;

            }

            $file_name = $this->key . '.csv';
            $file_path = trailingslashit($this->upload_dir) . $file_name;

            if (move_uploaded_file($temp_path, $file_path)) {

                $this->notice("✔️ Successfully uploaded '{$csv['name']}'.", 'updated');

            } else {

                $this->notice('📤 An unexpected error occured while uploading the file.', 'error');
                return;

            }

            chmod($file_path, 0644);

            $this->get_store();
            $this->store['file'] = [
                'name' => $csv['name'],
                'path' => $file_path,
            ];
            $this->update_store();

        }
    
    }

    public function warm_up () {

        parent::warm_up();

        if ($this->store['file']['path'] ?? 0) {

            $this->file = $this->store['file']['path'];

        }
        

    }

    protected function get_fields () {

        return [
            [
                'field'  => Field\Hidden::class,
                'key'    => 'nonce',
                'value'  => wp_create_nonce($this->key),
            ],
            [
                'field'  => Field\File::class,
                'label'  => 'Select CSV File',
                'key'    => 'csv',
                'accept' => '.csv',
            ],
            [
                'field' => Field\Submit::class,
                'text'  => 'Upload',
            ],
        ];

    }

    public function render_controller () {

        if (!$this->file) {

            echo "<style>" . file_get_contents(DIGITALIS_FRAMEWORK_PATH . '/assets/css/iterator.css') . "</style>";

            Form::render([
                'classes' => ['iterator-panel', 'intake'],
                'action'  => $_SERVER['REQUEST_URI'],
                'method'  => 'post',
                'attributes' => [
                    'enctype' => 'multipart/form-data',
                ],
                'fields' => $this->get_fields(),
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

        $response = parent::reset();
        $response['reload'] = true;
        return $response;

    }

}