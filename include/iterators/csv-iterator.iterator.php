<?php

namespace Digitalis;

abstract class CSV_Iterator extends Iterator {

    protected $file       = '';
    protected $delimiter  = ',';
    protected $enclosure  = "\"";
    protected $escape     = "\\";
    protected $has_header = true;
    protected $headers    = [];

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

}