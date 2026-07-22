<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Tools\Toolkits\MySQL;

use NeuronAi\Vendor\NeuronAI\Tools\Toolkits\AbstractToolkit;
use PDO;
/**
 * @method static static make(PDO $pdo)
 */
class MySQLToolkit extends AbstractToolkit
{
    public function __construct(protected PDO $pdo)
    {
    }
    public function guidelines(): ?string
    {
        return "These tools allow you to learn the database structure,\n        getting detailed information about tables, columns, relationships, and constraints\n        to generate and execute precise and efficient SQL queries for MySQL database.";
    }
    public function provide(): array
    {
        return [MySQLSchemaTool::make($this->pdo), MySQLSelectTool::make($this->pdo), MySQLWriteTool::make($this->pdo)];
    }
}
