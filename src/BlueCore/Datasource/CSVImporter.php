<?php
namespace BlueFission\BlueCore\Datasource;

use League\Csv\Reader;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CSVImporter
{
    protected $file;
    protected $data;
    protected $headers;
    protected $tableName;

    public function __construct($file, $tableName)
    {
        $this->file = $file;
        $this->tableName = $tableName;
        $this->analyze();
    }

    protected function analyze()
    {
        $pathInfo = pathinfo($this->file);

        if ($pathInfo['extension'] === 'csv') {
            $csv = Reader::createFromPath($this->file);
            $this->headers = $csv->fetchOne();
            $this->data = $csv->setOffset(1)->fetchAll();
        } else {
            $spreadsheet = IOFactory::load($this->file);
            $worksheet = $spreadsheet->getActiveSheet();
            $this->headers = $worksheet->extractedColumnNames();
            $this->data = $worksheet->extractedData();
        }
    }

    public function generateMigration()
    {
        $migrationGenerator = new MigrationGenerator();
        $migrationCode = $migrationGenerator->generateMigrationFromHeaders($this->tableName, $this->headers);

        // Save the generated migration code to a file
        $this->saveMigrationToFile($migrationCode);
    }

    protected function saveMigrationToFile($migrationCode)
    {
        // Determine the path and filename for the migration
        $timestamp = date('Y_m_d_His');
        $migrationFilename = $timestamp . "_create_{$this->tableName}_table.php";
        $migrationPath = "database/migrations/{$migrationFilename}";

        // Create the migration file with the required structure
        $fileContent = "<?php\n\nuse BlueFission\BlueCore\Datasource\Delta;\nuse BlueFission\Data\Storage\Structure\MysqlStructure as Structure;\nuse BlueFission\Data\Storage\Structure\MysqlScaffold as Scaffold;\n\nclass Create" . ucfirst($this->tableName) . "Table extends Delta\n{\n    public function change() {\n        " . $migrationCode . "\n    }\n\n    public function revert() {\n        Scaffold::delete('{$this->tableName}');\n    }\n}\n";

        // Save the file
        file_put_contents($migrationPath, $fileContent);
    }


    public function generateModel()
    {
        $modelGenerator = new ModelGenerator();
        $modelCode = $modelGenerator->generateModelFromHeaders($this->tableName, $this->headers);

        // Save the generated model code to a file
        $this->saveModelToFile($modelCode);
    }

    protected function saveModelToFile($modelCode)
    {
        // Determine the path and filename for the model
        $modelClassName = ucfirst($this->tableName) . "Model";
        $modelFilename = "{$modelClassName}.php";
        $modelPath = "app/Models/{$modelFilename}";

        // Create the model file with the required structure
        $fileContent = "<?php\n\nnamespace App\Models;\n\nuse BlueFission\BlueCore\Model\ModelSql as Model;\nuse BlueFission\Data\Storage\MysqlBulk;\n\nclass {$modelClassName} extends Model\n{\n    protected \$_table = ['{$this->tableName}'];\n    protected \$_fields = {$modelCode};\n\n    protected \$_ignore_null = true;\n\n    protected function init()\n    {\n        // Define any necessary relations or customizations here\n    }\n}\n";

        // Save the file
        file_put_contents($modelPath, $fileContent);
    }

    public function generateValueObject()
    {
        $valueObjectGenerator = new ValueObjectGenerator();
        $valueObjectCode = $valueObjectGenerator->generateValueObjectFromHeaders($this->tableName, $this->headers);

        // Save the generated ValueObject code to a file
        $this->saveValueObjectToFile($valueObjectCode);
    }

    protected function saveValueObjectToFile($valueObjectCode)
    {
        // Determine the path and filename for the ValueObject
        $valueObjectClassName = ucfirst($this->tableName);
        $valueObjectFilename = "{$valueObjectClassName}.php";
        $valueObjectPath = "app/Domain/{$this->tableName}/{$valueObjectFilename}";

        // Create the ValueObject file with the required structure
        $fileContent = "<?php\n\nnamespace App\Domain\\{$this->tableName};\n\nclass {$valueObjectClassName}\n{\n{$valueObjectCode}\n}\n";

        // Save the file
        file_put_contents($valueObjectPath, $fileContent);
    }

    public function importData()
    {
        // Import data into the generated table using $this->data
    }
}
