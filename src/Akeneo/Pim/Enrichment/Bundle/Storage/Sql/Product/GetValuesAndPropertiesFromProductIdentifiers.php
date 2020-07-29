<?php

declare(strict_types=1);

namespace Akeneo\Pim\Enrichment\Bundle\Storage\Sql\Product;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\Types\Type;

/**
 * SQL Query to get the properties and the values from a set of product identifiers:
 * identifier, is_enabled, product_model_code, created, updated, family_code, group_codes and raw_data.
 *
 * @author    Pierre Allard <pierre.allard@akeneo.com>
 * @copyright 2019 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
final class GetValuesAndPropertiesFromProductIdentifiers
{
    /** @var Connection */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function fetchByProductIdentifiers(array $productIdentifiers): array
    {
        $query = <<<SQL
SELECT
    p.id,
    p.identifier,
    p.is_enabled,
    pm1.code AS product_model_code,
    p.created,
    p.updated,
    f.code AS family_code,
    JSON_ARRAYAGG(g.code) AS group_codes,
    JSON_MERGE(COALESCE(pm1.raw_values, '{}'), COALESCE(pm2.raw_values, '{}'), p.raw_values) as raw_values
FROM pim_catalog_product p
LEFT JOIN pim_catalog_family f ON p.family_id = f.id
LEFT JOIN pim_catalog_group_product pg ON p.id = pg.product_id
LEFT JOIN pim_catalog_group g ON pg.group_id = g.id
LEFT JOIN pim_catalog_product_model pm1 ON p.product_model_id = pm1.id
LEFT JOIN pim_catalog_product_model pm2 ON pm1.parent_id = pm2.id
WHERE p.identifier IN (?)
GROUP BY p.id, p.identifier
SQL;

        $rows = $this->connection->fetchAll(
            $query,
            [$productIdentifiers],
            [Connection::PARAM_STR_ARRAY]
        );

        $platform = $this->connection->getDatabasePlatform();
        $results = [];
        foreach ($rows as $row) {
            $groupCodes = array_values(array_filter(json_decode($row['group_codes'])));
            sort($groupCodes);

            $results[$row['identifier']] = [
                'id' => Type::getType(Type::INTEGER)->convertToPHPValue($row['id'], $platform),
                'identifier' => Type::getType(Type::STRING)->convertToPHPValue($row['identifier'], $platform),
                'is_enabled' => Type::getType(Type::BOOLEAN)->convertToPHPValue($row['is_enabled'], $platform),
                'product_model_code' => Type::getType(Type::STRING)->convertToPHPValue($row['product_model_code'], $platform),
                'created' => Type::getType(Type::DATETIME_IMMUTABLE)->convertToPhpValue($row['created'], $platform),
                'updated' => Type::getType(Type::DATETIME_IMMUTABLE)->convertToPhpValue($row['updated'], $platform),
                'family_code' => Type::getType(Type::STRING)->convertToPHPValue($row['family_code'], $platform),
                'group_codes' => $groupCodes,
                'raw_values' => json_decode($row['raw_values'], true)
            ];
        }

        return $results;
    }

    /*******************************************************************************************************************
     * BEGIN POC
     */
    public function fetchAttributeCodesByTypes(array $types)
    {
        $query = <<<SQL
SELECT
    a.code
FROM pim_catalog_attribute a
WHERE a.attribute_type IN (?)
SQL;

        $attributeCodes = $this->connection->executeQuery(
            $query,
            [$types],
            [Connection::PARAM_STR_ARRAY]
        )->fetchAll(FetchMode::COLUMN);

        return $attributeCodes;
    }

    public function fetchAttributeOptionValuesByAttributeCodes(array $attributeCodes)
    {
        $query = <<<SQL
SELECT
       a.code as attribute_code,
       ao.code,
       aov.value,
       aov.locale_code,
       ao.sort_order
FROM pim_catalog_attribute_option_value aov
LEFT JOIN pim_catalog_attribute_option ao ON  ao.id = aov.option_id
LEFT JOIN pim_catalog_attribute a ON  ao.attribute_id = a.id
WHERE a.code IN (?)
SQL;

        $attributeOptionValues = $this->connection->executeQuery(
            $query,
            [$attributeCodes],
            [Connection::PARAM_STR_ARRAY]
        )->fetchAll(\PDO::FETCH_ASSOC | \PDO::FETCH_GROUP);

        return $attributeOptionValues;
    }
    /**
     * END POC
     ******************************************************************************************************************/
}
