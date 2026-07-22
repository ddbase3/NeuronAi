<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Tools\Toolkits\FileSystem;

use NeuronAi\Vendor\NeuronAI\Tools\PropertyType;
use NeuronAi\Vendor\NeuronAI\Tools\Tool;
use NeuronAi\Vendor\NeuronAI\Tools\ToolProperty;
use function file_get_contents;
use function is_file;
use function is_readable;
use function mb_strlen;
class ReadFileTool extends Tool
{
    public function __construct()
    {
        parent::__construct(name: 'read_file', description: 'Read the contents of a text file.');
    }
    protected function properties(): array
    {
        return [ToolProperty::make(name: 'file_path', type: PropertyType::STRING, description: 'Path to the file to read.')];
    }
    public function __invoke(string $file_path): string
    {
        if (!is_file($file_path)) {
            return "Error: File '{$file_path}' does not exist.";
        }
        if (!is_readable($file_path)) {
            return "Error: File '{$file_path}' is not readable.";
        }
        $content = file_get_contents($file_path);
        if ($content === \false) {
            return "Error: Unable to read file '{$file_path}'.";
        }
        $length = mb_strlen($content);
        return $content . "\n\n[File read successfully: {$length} characters]";
    }
}
