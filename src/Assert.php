<?php

/*
 * This file is part of the phpunit-json-assertions package.
 *
 * (c) Enrico Stahn <enrico.stahn@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace EnricoStahn\JsonAssert;

use JsonSchema\RefResolver;
use JsonSchema\Uri\UriRetriever;
use JsonSchema\Validator;

/**
 * Asserts to validate JSON data.
 *
 * - All assert methods expect deserialised JSON data (an actual object or array)
 *   since the deserialisation method should be up to the user.
 * - We provide a convenience method to transfer whatever into a JSON object (see ::getJsonObject(mixed))
 */
trait Assert
{
    /**
     * Asserts that json content is valid according to the provided schema file.
     *
     * Example:
     *
     *   static::assertJsonMatchesSchema(json_decode('{"foo":1}'), './schema.json')
     *
     * @param string       $schema  Path to the schema file
     * @param array|object $content JSON array or object
     */
    public static function assertJsonMatchesSchema($schema, $content)
    {
        $retriever = new UriRetriever();
        $schema = $retriever->retrieve('file://'.realpath($schema));

        $refResolver = new RefResolver($retriever);
        $refResolver->resolve($schema, 'file://'.__DIR__.'/../Resources/schemas/');

        $validator = new Validator();
        $validator->check($content, $schema);

        $message = '- Property: %s, Contraint: %s, Message: %s';
        $messages = array_map(function ($e) use ($message) {
            return sprintf($message, $e['property'], $e['constraint'], $e['message']);
        }, $validator->getErrors());
        $messages[] = '- Response: '.json_encode($content);

        self::assertTrue($validator->isValid(), implode("\n", $messages));
    }

    /**
     * Asserts that json content is valid according to the provided schema string.
     *
     * @param string       $schema  Schema data
     * @param array|object $content JSON content
     */
    public static function assertJsonMatchesSchemaString($schema, $content)
    {
        $file = tempnam(sys_get_temp_dir(), 'json-schema-');
        file_put_contents($file, $schema);

        self::assertJsonMatchesSchema($file, $content);
    }

    /**
     * Asserts if the value retrieved with the expression equals the expected value.
     *
     * Example:
     *
     *     static::assertJsonValueEquals(33, 'foo.bar[0]', $json);
     *
     * @param mixed        $expected   Expected value
     * @param string       $expression Expression to retrieve the result (e.g. locations[?state == 'WA'].name | sort(@) | {WashingtonCities: join(', ', @)})
     * @param array|object $json       JSON Content
     */
    public static function assertJsonValueEquals($expected, $expression, $json)
    {
        $result = self::search($expression, $json);

        self::assertEquals($expected, $result);
        self::assertInternalType(gettype($expected), $result);
    }

    /**
     * @param $expression
     * @param $data
     *
     * @return mixed|null
     */
    public static function search($expression, $data)
    {
        return \JmesPath\Env::search($expression, $data);
    }

    /**
     * Helper method to deserialise a JSON string into an object.
     *
     * @param mixed $data The JSON string
     *
     * @return array|object
     */
    public static function getJsonObject($data)
    {
        return (is_array($data) || is_object($data)) ? $data : json_decode($data);
    }
}
