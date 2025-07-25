<?php declare(strict_types=1);
/**
 * Copyright 2025 Alexey Kopytko
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

$header = <<<'EOF'
    Copyright 2025 Alexey Kopytko <alexey@kopytko.com>

    Licensed under the Apache License, Version 2.0 (the "License");
    you may not use this file except in compliance with the License.
    You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

    Unless required by applicable law or agreed to in writing, software
    distributed under the License is distributed on an "AS IS" BASIS,
    WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
    See the License for the specific language governing permissions and
    limitations under the License.
    EOF;

$config = new PhpCsFixer\Config();
$config
    ->setRiskyAllowed(true)
    ->setRules([
        'header_comment' => [
            'comment_type' => 'PHPDoc',
            'header' => $header,
            'separate' => 'bottom',
            'location' => 'after_declare_strict',
        ],
        '@PER-CS1.0' => true,
        'array_indentation' => true,
        'native_function_invocation' => [
            'include' => ['@internal'],
            'scope' => 'namespaced',
        ],
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => true,
        ],
        'declare_equal_normalize' => ['space' => 'none'],
        'blank_line_after_opening_tag' => false,
        'linebreak_after_opening_tag' => false,
        'nullable_type_declaration_for_default_null_value' => true,
    ])
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in(__DIR__)
            ->append([__FILE__])
    )
;

return $config;
