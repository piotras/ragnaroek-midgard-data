<?php

interface midgard_setup_database_interface
{
    public function create_database(string $database, string $username, string $password);

    public function update_database(string $database);

    public function import_file(string $file);

    public function import_sql(string $sql);
}
?>
