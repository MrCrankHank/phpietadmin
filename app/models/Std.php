<?php
namespace app\models;

use app\core;

class Std {
    private $logging;
    private $database;

    public function __construct() {
        $registry = core\Registry::getInstance();
        $this->logging = $registry->get('logging');
        $this->database = $registry->get('database');
    }

    /**
     * Recursive function to find a iqn in array
     *
     * @param string $iqn
     * @param array $haystack
     * @return int|bool
     */
    public function array_find_iqn($iqn, array $haystack) {
        foreach ($haystack as $key => $value) {
            if (false !== stripos($value, $iqn)) {
                // iqn is in $haystack[$key]
                // but we need to be sure
                // the first object and the iqn are separated by space
                // extract the iqn and compare it
                preg_match('([^\s]+)', $haystack[$key], $matches);

                if ($matches[0] === $iqn) {
                    return $key;
                } else {
                    unset($haystack[$key]);
                    $this->array_find_iqn($iqn, $haystack);
                }
            }
        }
        return false;
    }

    /**
     * array_search for multidimensional arrays
     *
     * @param string $needle
     * @param array $haystack
     * @return int|bool
     *
     */
    public function recursive_array_search($needle, array $haystack) {
        foreach ($haystack as $key => $value) {
            $current_key = $key;
            if ($needle === $value OR (is_array($value) && $this->recursive_array_search($needle, $value) !== false)) {
                return $current_key;
            }
        }
        return false;
    }

    /**
     *  array_search function with partial match
     *
     * @param string $needle
     * @param array $haystack
     * @link https://gist.github.com/branneman/951847
     * @return bool
     *
     */
    public function array_find($needle, array $haystack) {
        foreach ($haystack as $key => $value) {
            if (false !== stripos($value, $needle)) {
                return true;
            }
        }
        return false;
    }

    /**
     *
     * Escape and execute a command
     * $return['status'] = string, contains a error message from the program executed
     * $return['result'] = int contains a error code from the program executed
     * $return['code_type'] = error code generated by third party tool or phpietadmin?
     *
     * @param    string $command command to be executed
     * @return   array
     *
     */
    public function exec_and_return($command) {
        $return = [];
        $this->logging->log_debug_result();
        exec(escapeshellcmd($command) . ' 2>&1', $return['status'], $return['result']);
        $return['code_type'] = 'extern';
        return $return;
    }

    /**
     *
     * empty() function for multiple values
     *
     * @link    http://stackoverflow.com/questions/4993104/using-ifempty-with-multiple-variables-not-in-an-array
     * @return      boolean
     *
     */
    public function mempty() {
        foreach (func_get_args() as $arg)
            //if (!isset($arg)) {
            //    continue;
            //} else
            if (empty($arg))
                continue;
            else
                return false;
        return true;
    }

    /**
     * Create a "normal" array from a multidimensional one
     *
     * @param array $array multidimensional array to convert
     * @return array
     * @link http://stackoverflow.com/questions/6785355/convert-multidimensional-array-into-single-array/6785366#6785366
     *
     */
    public function array_flatten(array $array) {
        if (!is_array($array)) {
            return FALSE;
        }
        $result = array();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result = array_merge($result, $this->array_flatten($value));
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    public function check_if_file_contains_value($file, $value) {
        if (strpos(file_get_contents($file), $value) !== false) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Tail implementation in php
     *
     * @link http://www.geekality.net/2011/05/28/php-tail-tackling-large-files/
     * @param $filename
     * @param int $lines
     * @param int $buffer
     * @return string
     */
    public function tail($filename, $lines = 10, $buffer = 4096) {
        if (file_exists($filename) && filesize($filename) != 0) {
            // Open the file
            $f = fopen($filename, "rb");

            // Jump to last character
            fseek($f, -1, SEEK_END);

            // Read it and adjust line number if necessary
            // (Otherwise the result would be wrong if file doesn't end with a blank line)
            if (fread($f, 1) != "\n") $lines -= 1;

            // Start reading
            $output = '';
            $chunk = '';

            // While we would like more
            while (ftell($f) > 0 && $lines >= 0) {
                // Figure out how far back we should jump
                $seek = min(ftell($f), $buffer);

                // Do the jump (backwards, relative to where we are)
                fseek($f, -$seek, SEEK_CUR);

                // Read a chunk and prepend it to our output
                $output = ($chunk = fread($f, $seek)) . $output;

                // Jump back to where we started reading
                fseek($f, -mb_strlen($chunk, '8bit'), SEEK_CUR);

                // Decrease our line counter
                $lines -= substr_count($chunk, "\n");
            }

            // While we have too many lines
            // (Because of buffer size we might have read too many)
            while ($lines++ < 0) {
                // Find first newline and remove all text before that
                $output = substr($output, strpos($output, "\n") + 1);
            }

            // Close file and return
            fclose($f);

            $data = array_filter(explode("\n", $output));

            foreach ($data as $line) {
                $rows[] = str_getcsv($line, ' ');
            }

            return $rows;
        } else {
            return false;
        }
    }

    /**
     * Backup a file to the phpietadmin backup dir
     * Only $maxBackups will be stored, before the oldest is deleted
     *
     * @param        $path
     * @param string $type
     * @return bool
     */
    public function backupFile($path, $type = 'file') {
        $backupDir = $this->database->get_config('backupDir')['value'];
        $maxBackups = $this->database->get_config('maxBackups')['value'];
        $backupDirFiles = $backupDir . '/files';
        $backupDirDb = $backupDir . '/db';

        // Create backup folder
        if (!is_dir($backupDirFiles)) {
            mkdir($backupDirFiles);
        }
        if (!is_dir($backupDirDb)) {
            mkdir($backupDirDb);
        }

        // Delete old backup files, but keep at least $maxBackups
        $files = glob($backupDirFiles . '/*');
        array_multisort(array_map('filemtime', $files), SORT_NUMERIC, SORT_ASC, $files);
        if (count($files) >= $maxBackups) {
            if (file_exists($files[0])) {
                unlink($files[0]);
            }
        }

        // Delete old db backups, but keep at least $maxBackups
        $files = glob($backupDirDb . '/*');
        array_multisort(array_map('filemtime', $files), SORT_NUMERIC, SORT_ASC, $files);
        if (count($files) > $maxBackups) {
            if (file_exists($files[0])) {
                unlink($files[0]);
            }
        }

        if ($type === 'file') {
            if (file_exists($path)) {
                $filename = array_pop(explode('/', $path));
                return copy($path, $backupDirFiles . '/' . $filename . '_' . time());
            } else {
                return false;
            }
        } else if ($type === 'db') {
            if (file_exists($path)) {
                $filename = array_pop(explode('/', $path));
                return copy($path, $backupDirDb . '/' . $filename . '_' . time());
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
}