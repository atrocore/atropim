<?php

$dir = dirname(dirname(dirname(dirname(dirname(__FILE__)))));

chdir($dir);
set_include_path($dir);

require_once 'vendor/autoload.php';

$container = (new \Espo\Core\Application())->getContainer();

/** @var \PDO $pdo */
$pdo = $container->get('entityManager')->getPDO();

$auth = new \Espo\Core\Utils\Auth($container);
$auth->useNoAuth();

$productService = $container->get('serviceFactory')->create('Product');

try {
    $records = $pdo->query("SELECT * FROM `product` WHERE configurable_product_id IS NOT NULL AND deleted=0")->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    $records = [];
}

$unInheritedRelations = $container->get('entityManager')->getRepository('Product')->getUnInheritedRelations();

$inheritedRelations = [];
foreach ($container->get('metadata')->get(['entityDefs', 'Product', 'links'], []) as $link => $linkData) {
    if (!empty($linkData['type']) && $linkData['type'] === 'hasMany' && !in_array($link, $unInheritedRelations)) {
        $inheritedRelations[] = $link;
    }
}

foreach ($records as $record) {
    try {
        $pdo->exec("INSERT INTO `product_hierarchy` (entity_id, parent_id, deleted) VALUES ('{$record['id']}','{$record['configurable_product_id']}', 0)");
    } catch (\Throwable $e) {
    }

    $pdo->exec("UPDATE `product` SET has_inconsistent_attributes=1 WHERE id IN ('{$record['id']}','{$record['configurable_product_id']}')");

    $data = @json_decode((string)$record['data'], true);
    $customRelations = !empty($data['customRelations']) ? $data['customRelations'] : [];

    foreach ($inheritedRelations as $link) {
        if (!in_array($link, $customRelations)) {
            try {
                $productService->inheritAll($record['id'], $link);
            } catch (\Throwable $e) {
            }
        }
    }

    echo "'{$record['mpn']}' migrated." . PHP_EOL;
}

echo "Done." . PHP_EOL;