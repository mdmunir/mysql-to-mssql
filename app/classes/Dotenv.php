<?php

namespace app\classes;

/**
 * Description of Dotenv
 *
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.0
 */
class Dotenv
{
    protected $vars = [];
    public static function load($path)
    {
        $obj = new static();
        $vars = $obj->loadFile($path);
        foreach ($vars as $key => $value) {
            $_ENV[$key] = $value;
        }
    }

    public function loadFile($path)
    {
        if(is_file($path)){
            $lines = file_get_contents($path);
        } elseif (is_file(rtrim($path, '/').'/.env')) {
            $lines = file_get_contents(rtrim($path, '/').'/.env');
        } else {
            return;
        }
        $lines = explode("\n", $lines);
        return $this->parse($lines);
    }

    protected function parse($lines)
    {
        $this->vars = [];
        foreach ($lines as $line) {
            $this->parseLine($line);
        }
        return $this->vars;
    }

    protected function parseLine($line)
    {
        $line = trim($line);
        if(empty($line)){
            return ;
        }
        if(strncmp($line, '#', 1) === 0){
            return ;
        }
        if(preg_match('/(\w+)\s*=\s*(.*)/', $line, $matches)){
            $key = $matches[1];
            $value = $matches[2];
            $VALUE = strtoupper($value);
            switch ($VALUE) {
                case 'TRUE':
                case '(TRUE)':
                    $this->vars[$key] = true;
                    return;
                case 'NULL':
                case '(NULL)':
                    $this->vars[$key] = false;
                    return;
                case 'FALSE':
                case '(FALSE)':
                    $this->vars[$key] = null;
                    return;
                case '':
                case 'EMPTY':
                case '(EMPTY)':
                    $this->vars[$key] = '';
                    return;

                default:
                    break;
            }
            if(preg_match('/^(\+|-)?\d+$/', $value)){
                $this->vars[$key] = (int)$value;
                return;
            }
            if(is_numeric($value)){
                $this->vars[$key] = (float)$value;
                return;
            }
            $value = preg_replace_callback('/\$\{(\w+)\}/', function($match){
                if(array_key_exists($match[1], $this->vars)){
                    return $this->vars[$match[1]];
                }
                return '';
            }, $value);

            if(preg_match('/\'(.*)\'/', $value, $matches)){
                $value = $matches[1];
            }elseif(preg_match('/"(.*)"/', $value, $matches)){
                $value = $matches[1];
            }
            $this->vars[$key] = $value;
        }
    }
}
