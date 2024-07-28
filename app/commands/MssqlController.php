<?php

namespace app\commands;

use PDO;
use Yii;
use yii\base\InvalidArgumentException;
use yii\console\Controller;
use yii\db\Connection;
use yii\db\Query;
use yii\di\Instance;
use yii\helpers\Console;
use yii\helpers\FileHelper;
use yii\helpers\Inflector;

/**
 * Description of MssqlController
 *
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.0
 */
class MssqlController extends Controller
{
    /**
     *
     * @var string path table list config
     */
    public $taskPath = '@root/tasks';
    /**
     *
     * @var string output path
     */
    public $outputPath = '@runtime/output';
    /**
     *
     * @var int row count per insert statement
     */
    public $batchSize = 100;
    /**
     * 
     * @var int max row per file
     */
    public $maxLine = 1000000;
    /**
     *
     * @var bool show progress bar
     */
    public $progress = true;

    /**
     * Export data table to sql file
     * @param string $name Task name
     * @throws InvalidArgumentException
     */
    public function actionExportToFile($name)
    {
        $map = $this->parseTables($name);
        if ($map === false) {
            throw new InvalidArgumentException("Invalid '$name' parameter value.");
        }
        /** @var Connection $dbSource */
        $dbSource = Instance::ensure('dbSource', Connection::class);
        $sourceSchema = $dbSource->schema;

        /** @var Connection $dbDest */
        /** @var \yii\db\mssql\Schema $destSchema */
        $dbDest = Instance::ensure('dbDest', Connection::class);
        $destSchema = $dbDest->schema;

        $total = 0;
        foreach ($map as $from => $config) {
            $to = $config['to'];
            $tableSchema = $sourceSchema->getTableSchema($from);
            if (!$tableSchema) {
                throw new InvalidArgumentException("Unknown table '$from'");
            }
            $count = (new Query())
                ->from($from)
                ->count('*', $dbSource);
            $total += $count;
        }
        $templateFile = rtrim(Yii::getAlias($this->outputPath), '/') . date('/Ymd_His_') . "{$name}-{group}.sql";
        FileHelper::createDirectory(dirname($templateFile));
        $group = 1;
        $i = 0;
        $ii = 0;
        $done = 0;
        $fname = strtr($templateFile, ['{group}' => sprintf('%02d', $group)]);
        if ($this->progress) {
            Console::startProgress($done, $total);
        }
        $buffer = '';
        foreach ($map as $from => $to) {
            $tableSchema = $sourceSchema->getTableSchema($from);
            $columns = $tableSchema->columns;
            $sourceColumns = [];
            $quoteColumns = [];
            $types = [];
            $orders = [];
            $ii = 0;
            $sequenceColumn = null;
            $varId = null;
            $quoteTo = $destSchema->quoteTableName($to);
            foreach ($columns as $column) {
                $quoteColumns[] = $destSchema->quoteColumnName($column->name);
                $sourceColumns[] = $column->name;
                $types[] = $column->type;
                if ($column->isPrimaryKey) {
                    $orders[$column->name] = SORT_ASC;
                }
                if ($column->autoIncrement) {
                    $sequenceColumn = $destSchema->quoteColumnName($column->name);
                    $varId = Inflector::variablize($from . '_' . $column->name);
                }
            }
            $sql = "TRUNCATE TABLE $quoteTo;\nGO\n";
            $buffer = $sql;
            if ($sequenceColumn) {
                $buffer .= "SET IDENTITY_INSERT $quoteTo ON\n\n";
            }
            $insert = "INSERT INTO $quoteTo(" . implode(', ', $quoteColumns) . ") VALUES\n";

            $query = (new Query())
                ->select($sourceColumns)
                ->from($from);
            if (count($orders)) {
                $query->orderBy($orders);
            }
            $reader = $query->createCommand($dbSource)->query();
            $reader->setFetchMode(PDO::FETCH_NUM);
            $lines = [];
            while ($row = $reader->read()) {
                $ii++;
                $i++;
                $done++;
                $line = $this->convertRow($row, $types);
                $lines[] = "\t($line)";
                if ($ii >= $this->batchSize) {
                    $buffer .= $insert . implode(",\n", $lines) . ";\nGO\n\n";
                    file_put_contents($fname, $buffer, FILE_APPEND);
                    $buffer = '';
                    $ii = 0;
                    $lines = [];
                    if ($this->progress) {
                        Console::updateProgress($done, $total, $from);
                    }
                }
            }
            if (count($lines)) {
                $buffer .= $insert . implode(",\n", $lines) . ";\nGO\n\n";
                file_put_contents($fname, $buffer, FILE_APPEND);
                $buffer = '';
                $lines = [];
                $ii = 0;
                if ($this->progress) {
                    Console::updateProgress($done, $total, $from);
                }
            }

            if ($sequenceColumn) {
                $buffer = "SET IDENTITY_INSERT $quoteTo OFF\nGO\n";
//                $sql = <<<SQL
//DECLARE @{$varId} INT;
//SELECT @{$varId} = COALESCE(MAX({$sequenceColumn}),0)+1 FROM {$quoteTo};
//
//DBCC CHECKIDENT ('{$quoteTo}', RESEED, @{$varId});
//GO
//
//SQL;
//                $buffer .= $sql;
                file_put_contents($fname, $buffer, FILE_APPEND);
            }

            if ($i >= $this->maxLine) {
                $group++;
                $fname = strtr($templateFile, ['{group}' => sprintf('%02d', $group)]);
                $i = 0;
            }
        }
        if ($this->progress) {
            Console::endProgress();
        }
    }

    /**
     * Export data table directly to database
     * @param string $name Task name
     * @throws InvalidArgumentException
     */
    public function actionExportCopy($name)
    {
        $map = $this->parseTables($name);
        if ($map === false) {
            throw new InvalidArgumentException("Invalid '$name' parameter value.");
        }
        /** @var Connection $dbSource */
        $dbSource = Instance::ensure('dbSource', Connection::class);
        $sourceSchema = $dbSource->schema;

        /** @var Connection $dbDest */
        /** @var \yii\db\mssql\Schema $destSchema */
        $dbDest = Instance::ensure('dbDest', Connection::class);
        $destSchema = $dbDest->schema;
        $destCommand = $dbDest->createCommand();

        $total = 0;
        foreach ($map as $from => $to) {
            $tableSchema = $sourceSchema->getTableSchema($from);
            if (!$tableSchema) {
                throw new InvalidArgumentException("Unknown table '$from'");
            }
            $count = (new Query())
                ->from($from)
                ->count('*', $dbSource);
            $total += $count;
        }

        $i = 0;
        $ii = 0;
        $done = 0;
        if ($this->progress) {
            Console::startProgress($done, $total);
        }

        foreach ($map as $from => $config) {
            $to = $config['to'];
            $tableSchema = $sourceSchema->getTableSchema($from);
            $columns = $tableSchema->columns;
            $sourceColumns = [];
            $quoteColumns = [];
            $types = [];
            $orders = [];
            $ii = 0;
            $sequenceColumn = null;
            $varId = null;
            $quoteTo = $destSchema->quoteTableName($to);
            foreach ($columns as $column) {
                $quoteColumns[] = $destSchema->quoteColumnName($column->name);
                $sourceColumns[] = $column->name;
                $types[] = $column->type;
                if ($column->isPrimaryKey) {
                    $orders[$column->name] = SORT_ASC;
                }
                if ($column->autoIncrement) {
                    $sequenceColumn = $destSchema->quoteColumnName($column->name);
                    $varId = Inflector::variablize($from . '_' . $column->name);
                }
            }
            $sql = "TRUNCATE TABLE $quoteTo";
            $destCommand->setSql($sql)->execute();
            $insert = '';
            if ($sequenceColumn) {
                $insert = "SET IDENTITY_INSERT $quoteTo ON;\n";
                //$destCommand->setSql($sql)->execute();
            }
            $insert .= "INSERT INTO $quoteTo(" . implode(', ', $quoteColumns) . ") VALUES\n";

            $query = (new Query())
                ->select($sourceColumns)
                ->from($from);
            if (count($orders)) {
                $query->orderBy($orders);
            }
            $reader = $query->createCommand($dbSource)->query();
            $reader->setFetchMode(PDO::FETCH_NUM);
            $lines = [];
            while ($row = $reader->read()) {
                $ii++;
                $i++;
                $done++;
                $line = $this->convertRow($row, $types);
                $lines[] = "\t($line)";
                if ($ii >= $this->batchSize) {
                    $sql = $insert . implode(",\n", $lines);
                    $destCommand->setSql($sql)->execute();
                    $ii = 0;
                    $lines = [];
                    if ($this->progress) {
                        Console::updateProgress($done, $total, $from);
                    }
                }
            }
            if (count($lines)) {
                $sql = $insert . implode(",\n", $lines);
                $destCommand->setSql($sql)->execute();
                $lines = [];
                $ii = 0;
                if ($this->progress) {
                    Console::updateProgress($done, $total, $from);
                }
            }

            if ($sequenceColumn) {
                $sql = "SET IDENTITY_INSERT $quoteTo OFF";
                $destCommand->setSql($sql)->execute();
//                $sql = <<<SQL
//DECLARE @{$varId} INT;
//SELECT @{$varId} = COALESCE(MAX({$sequenceColumn}),0)+1 FROM {$quoteTo};
//
//DBCC CHECKIDENT ('{$quoteTo}', RESEED, @{$varId});
//GO
//
//SQL;
//                $destCommand->setSql($sql)->execute();
            }
        }
        if ($this->progress) {
            Console::endProgress();
        }
    }

    /**
     * Export data table directly to database using MERGE statement.
     * @param string $name Task name
     * @throws InvalidArgumentException
     */
    public function actionExportMerge($name)
    {
        $map = $this->parseTables($name);
        if ($map === false) {
            throw new InvalidArgumentException("Invalid '$name' parameter value.");
        }
        /** @var Connection $dbSource */
        $dbSource = Instance::ensure('dbSource', Connection::class);
        $sourceSchema = $dbSource->schema;

        /** @var Connection $dbDest */
        /** @var \yii\db\mssql\Schema $destSchema */
        $dbDest = Instance::ensure('dbDest', Connection::class);
        $destSchema = $dbDest->schema;
        $destCommand = $dbDest->createCommand();

        $total = 0;
        $whereMap = [];
        foreach ($map as $from => $config) {
            $to = $config['to'];
            $tableSchema = $sourceSchema->getTableSchema($from);
            if (!$tableSchema) {
                throw new InvalidArgumentException("Unknown table '$from'");
            }
            $where = [];
            if ($keys = $tableSchema->primaryKey) {
                if (!empty($config['begin'])) {
                    $values = explode(',', $config['begin'], count($keys));
                    foreach ($values as $i => $value) {
                        $where[] = ['>=', $keys[$i], $value];
                    }
                }
                if (!empty($config['end'])) {
                    $values = explode(',', $config['end'], count($keys));
                    foreach ($values as $i => $value) {
                        $where[] = ['<=', $keys[$i], $value];
                    }
                }
                if (count($where)) {
                    array_unshift($where, 'AND');
                    $whereMap[$from] = $where;
                }
            }

            $query = (new Query())
                ->from($from);
            if ($where) {
                $query->andWhere($where);
            }
            $count = $query->count('*', $dbSource);
            $total += $count;
        }

        $PROGRESS_STEP = min($total / 1000, 1000);
        $i = 0;
        $ii = 0;
        $done = 0;
        $progress = 0;
        if ($this->progress) {
            Console::startProgress($done, $total);
        }

        foreach ($map as $from => $config) {
            $to = $config['to'];
            $tableSchema = $sourceSchema->getTableSchema($from);
            $columns = $tableSchema->columns;
            $sourceColumns = [];
            $quoteColumns = [];
            $types = [];
            $orders = [];
            $ii = 0;
            $sequenceColumn = null;
            $varId = null;
            $quoteTo = $destSchema->quoteTableName($to);
            $primaries = $updates = $inserts = [];
            foreach ($columns as $column) {
                $columnName = $destSchema->quoteColumnName($column->name);
                $quoteColumns[] = $columnName;
                $sourceColumns[] = $column->name;
                $types[] = $column->type;
                if ($column->isPrimaryKey) {
                    $orders[$column->name] = SORT_ASC;
                    $primaries[] = "target.$columnName = source.$columnName";
                } else {
                    $updates[] = "target.$columnName = source.$columnName";
                }
                $inserts[] = "source.$columnName";
                if ($column->autoIncrement) {
                    $sequenceColumn = $destSchema->quoteColumnName($column->name);
                    $varId = Inflector::variablize($from . '_' . $column->name);
                }
            }

            $prefix = '';
            if ($sequenceColumn) {
                $prefix = "SET IDENTITY_INSERT $quoteTo ON;\n";
            }
            $quoteColumnsList = implode(', ', $quoteColumns);
            $primaryList = implode(" AND ", $primaries);
            $updateList = implode(",\n\t", $updates);
            $insertList = implode(",\n\t", $inserts);

            if(count($updates)){
                $mergeSql = <<<SQL
{$prefix}MERGE INTO $quoteTo AS target
USING (VALUES {{VALUES}}) AS source($quoteColumnsList)
ON $primaryList
WHEN MATCHED THEN
    UPDATE SET
    $updateList
WHEN NOT MATCHED THEN
    INSERT ($quoteColumnsList)
    VALUES ($insertList);
SQL;
            } else{
                $mergeSql = <<<SQL
{$prefix}MERGE INTO $quoteTo AS target
USING (VALUES {{VALUES}}) AS source($quoteColumnsList)
ON $primaryList
WHEN NOT MATCHED THEN
    INSERT ($quoteColumnsList)
    VALUES ($insertList);
SQL;
            }

            $query = (new Query())
                ->select($sourceColumns)
                ->from($from);
            if (isset($whereMap[$from])) {
                $query->andWhere($whereMap[$from]);
            }
            if (count($orders)) {
                $query->orderBy($orders);
            }
            $reader = $query->createCommand($dbSource)->query();
            $reader->setFetchMode(PDO::FETCH_NUM);
            $lines = [];
            while ($row = $reader->read()) {
                $ii++;
                $i++;
                $done++;
                $progress++;
                $line = $this->convertRow($row, $types);
                $lines[] = "($line)";
                if ($ii >= $this->batchSize) {
                    $insertValues = implode(",\n\t", $lines);
                    $sql = str_replace('{{VALUES}}', $insertValues, $mergeSql);
                    $destCommand->setSql($sql)->execute();
                    $ii = 0;
                    $lines = [];
                    if ($this->progress && $progress >= $PROGRESS_STEP) {
                        Console::updateProgress($done, $total, $from);
                        $progress = 0;
                    }
                }
            }
            if (count($lines)) {
                $insertValues = implode(",\n\t", $lines);
                $sql = str_replace('{{VALUES}}', $insertValues, $mergeSql);
                $destCommand->setSql($sql)->execute();
                $lines = [];
                $ii = 0;
                if ($this->progress) {
                    Console::updateProgress($done, $total, $from);
                    $progress = 0;
                }
            }

            if ($sequenceColumn) {
                $sql = "SET IDENTITY_INSERT $quoteTo OFF";
                $destCommand->setSql($sql)->execute();
//                $sql = <<<SQL
//DECLARE @{$varId} INT;
//SELECT @{$varId} = COALESCE(MAX({$sequenceColumn}),0)+1 FROM {$quoteTo};
//
//DBCC CHECKIDENT ('{$quoteTo}', RESEED, @{$varId});
//GO
//
//SQL;
//                $destCommand->setSql($sql)->execute();
            }
        }
        if ($this->progress) {
            Console::endProgress();
        }
    }

    protected function convertRow($row, $types)
    {
        // INSERT [dbo].[Hris_TCuti] ([ct_id], [ct_kode], [ct_tran], [mk_nopeg], [ct_from], [ct_to], [ct_korin], [ct_notes], [ct_address],
        // [ct_create], [ct_createby], [ct_update], [ct_updateby], [ct_app1_unit], [ct_app1_time], [ct_app1_by], [ct_app1_status], [ct_app2_unit], [ct_app2_time], [ct_app2_by], [ct_app2_status], [status], [koreksi], [status_sap], [jml_hari_cuti], [mk_nopeg_delegasi])
        // VALUES (1, N'0110', N'A', N'00001736', CAST(N'2012-05-01T00:00:00.000' AS DateTime), CAST(N'2012-05-01T00:00:00.000' AS DateTime), NULL, NULL, N'GRESIK', CAST(N'2012-05-01T13:15:16.000' AS DateTime), N'00001736', CAST(N'2012-05-16T07:43:30.000' AS DateTime), N'00005825', N'50000117', CAST(N'2012-05-16T07:43:30.000' AS DateTime), N'00005825', N'A', NULL, NULL, NULL, NULL, N'Y', NULL, N'1', 1, NULL)
        $line = [];
        foreach ($row as $c => $value) {
            if ($value === null) {
                $line[] = 'NULL';
            } else {
                switch ($types[$c]) {
                    case 'date':
                        $v = str_replace('0000-00-00', '1900-01-01', $value);
                        $line[] = "CAST(N'$v' AS Date)";
                        break;
                    case 'datetime':
                        $v = str_replace('0000-00-00', '1900-01-01', $value);
                        $line[] = "CAST(N'$v' AS DateTime)";
                        break;
                    case 'integer':
                        $line[] = (int) $value;
                        break;
                    case 'float':
                    case 'double':
                        $line[] = (double) $value;
                        break;
                    default :
                        $v = str_replace("'", "''", $value);
                        $line[] = "N'$v'";
                }
            }
        }
        return implode(", ", $line);
    }

    protected function parseTables($task)
    {
        $path = rtrim(Yii::getAlias($this->taskPath), '/');
        if (is_file("$path/$task")) {
            $lines = trim(file_get_contents("$path/$task"));
            $lines = explode("\n", $lines);

            $result = [];
            foreach ($lines as $line) {
                $filters = [];
                $line = trim($line);
                if (!$line || strncmp($line, '#', 1) === 0) {
                    continue;
                }
                // parse filter
                $parts = explode(';', $line);
                for ($i = 1; $i < count($parts); $i++) {
                    $part = trim($parts[$i]);
                    if (empty($part)) {
                        continue;
                    }
                    if (preg_match('/(begin|end)\s*=*\s*(.+)/', $part, $matches)) {
                        $filters[$matches[1]] = $matches[2];
                    }
                }

                $row = explode('=', $parts[0], 2);
                $from = trim($row[0]);
                $to = isset($row[1]) ? trim($row[1]) : false;
                $filters['to'] = empty($to) ? $from : $to;
                $result[$from] = $filters;
            }
            return $result;
        }
        return false;
    }

    public function options($actionID)
    {
        $options = parent::options($actionID);
        switch ($actionID) {
            case 'export-to-file':
                return array_merge($options, ['taskPath', 'outputPath', 'batchSize', 'maxLine', 'progress']);
            case 'export-copy':
                return array_merge($options, ['taskPath', 'batchSize', 'progress']);
            case 'export-merge':
                return array_merge($options, ['taskPath', 'batchSize', 'progress']);

            default:
                break;
        }
        return $options;
    }

    public function optionAliases()
    {
        return array_merge(parent::optionAliases(), [
            'p' => 'progress',
            's' => 'batchSize',
            'o' => 'outputPath',
        ]);
    }
}
