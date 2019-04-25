<?php
class Sync{

    protected $filename = 'backup.sql'; //name of the file that will be saved and import

    protected $localDB;  // database export from
    protected $onlineDB; // database import from
    protected $locationExport; // location of the file how have the database query {Export}
    protected $locationImport; // location of the file how have the database query {Import}
    protected $return;   // the Variable how have the database query


    /*
     * if you need to use (import function) you must enter in this array
     * the name of table you need to insert into online database
     * */
    protected $syncTable =[
        'invoices','items','m_invoices','m_items',
    ];

    /*
     * Sync constructor.
     * $local  ->name of database you need to export
     * $online ->name of database you need to import with the {file}
     * **( {file} will be create in (export function) and upload with (import function) )**
     */
    function __construct($local, $online, $locationE, $locationI){
        try {

            $this->localDB = new PDO("mysql:host=localhost;dbname={$local}", "root", "",
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION) );
            $this->onlineDB = new PDO("mysql:host=localhost;dbname={$online}", "root", "",
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION) );

        } catch (PDOException $exception) {
            die($exception->getMessage());
        }

        $this->locationExport = $locationE;
        $this->locationImport = $locationI;

    }

    ////////////////////////////////////////////////////////////////////////////////////////

    function export($isImport = false){

        $tables = array();

        if(!$isImport) {

            $sql = $this->localDB->prepare("SHOW TABLES");

            $sql->execute();

            /*
            * copy the name of each table in the database to the
            * $tables variable
            * */

            while ($tables[] = $sql->fetch(PDO::FETCH_COLUMN)) ;


            /*
             * the last value that come from (PDO::FETCH_COLUMN)WILL BE A BOOLEAN
             * TRUE OR FALSE however we do not need this value in our array so
             * i will remove it in the next line
             **/

            array_pop($tables);

        }else {
            $tables = $this->syncTable; //if we need to import data the table name must be entered first in $syncTable
        }



        /*
         * this block of code is used to cope the name of table in the online database
         * and it will use to compeer with the name of table in the local database
         * to add (DROP TABLE {name of table}) or not in to {$return}
         * */
        {
            $tdo = array();

            $sql = $this->onlineDB->prepare("SHOW TABLES");

            $sql->execute();

            while ($tdo[] = $sql->fetch(PDO::FETCH_COLUMN)) ;

            array_pop($tdo);

        }


        // The variable that contains the database code

        $this->return = '';

        foreach ($tables as $table) {

            $checkIfExisting = 0; // 0 => table is not Existing
            // 1 => table in Existing

            foreach ($tdo as $c)
                if($c == $table)
                    $checkIfExisting++;

            if($checkIfExisting == 1 && $isImport )
                $this->return .= 'DROP TABLE '.$table.';'; // this line -_-


            $sql = $this->localDB->prepare("SHOW CREATE TABLE $table");

            $sql->execute();

            $result = $sql->fetchAll(PDO::FETCH_ASSOC);

            /*
             * (PDO::FETCH_ASSOC) will fetch the database
             * and return the value as association array
             * however it will return each table as association array
             * and we have many table so it will throe it into another
             * array and because the line of code is repeated
             * for each table the result will be in index[0] always
             * the next line cope this result to be abel to use
             *  association array properties {$result1["Create Table"]}
             * (It takes from you three hours to fix it
             *  So give her some attention)
             * */

            $result1 = $result[0]; // this line take 3h

            $this->return .= "\n\n" . $result1["Create Table"] . ";\n\n";

            $sql = $this->localDB->prepare("SELECT * FROM  $table");

            $sql->execute();

            $results = $sql->fetchAll(PDO::FETCH_ASSOC);

            //this block of code used to insert the data

            foreach ($results as $result) {

                $this->return .= "INSERT INTO " . $table . " VALUES(";

                $counter = 0;
                foreach ($result as $k => $v) {
                    $v = addslashes($v);
                    if ($v !== "") {
                        $this->return .= ' "' . $v . '"';
                    } else {
                        $this->return .= ' NULL';
                    }
                    if ($counter == count($result) - 1) {
                        break;
                    } else
                        $this->return .= ",";
                    $counter++;
                }
                $this->return .= "); \n";
            }

            $this->return .= "\n\n\n";

        }


        if(!$isImport) { // do not save the file if the (export function) is called from (import function)
            //save file
            $handle = fopen($this->filename, 'w+');
            fwrite($handle, $this->return);
            fclose($handle);


            /*
             * try to move the export file to the location $locationExport
             * */
            try {
                rename($this->filename, "{$this->locationExport}/{$this->filename}");
            } catch (Exception $e) {
                die($e->getMessage());
            }
        }


    }

    ////////////////////////////////////////////////////////////////////////////////

    function import($importFrom = false){   // chose import from file in the system or from {$localDB}
        // $importFrom is boolean  (true=>from $localDB // false => from system )


        /*
         * chose the database query source
         * */
        if($importFrom) { //from the $localDB

            $this->export($this->syncTable);

            $sql = explode(';', $this->return);
            array_pop($sql);

        }else{ // from the file in the system saved in location $locationImport


            try {
                $handle = fopen("{$this->locationImport}/{$this->filename}", "r+");
            }catch (Exception $e){
                die($e->getMessage());
            }

            $contents = fread($handle,filesize("{$this->locationImport}/{$this->filename}"));
            $sql = explode(';',$contents);
            array_pop($sql);
        }

        foreach ($sql as $query) {
            $result = $this->onlineDB->prepare("$query");
            try{
                $result->execute();
            }catch (Exception $e){
                echo $e;
            }

        }

    }

    ///////////////////////////////////////////////////////////////////////////////////

    /* this function is used to import from outside  */

    function importFromOutside()
    {

        if(!empty($_POST['return'])){

            $sql = explode(';', $_POST['return']);

            foreach ($sql as $query) {
                $result = $this->onlineDB->prepare("$query");
                try {
                    $result->execute();
                } catch (Exception $e) {
                    echo $e;
                }
            }

        }else
            echo 'there is no database';

    }

    ////////////////////////////////////////////////////////////////////////////////

    public function getReturn(){

        return $this->return;

    }

}

$a = new Sync('t1','ggg', 'move' ,'move' );

//$a->export(); ////export  database with name {online} and save file in location locationE

$a->import(1); //import to the database with name {online} and call the (export function)
