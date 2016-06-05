<?
/**
 * Хелперы для инфоблоков
 *
 * @copyright 2015 Dmitry Doroshev
 */

/**
 * Обёртка, кэширующая результаты _getIblocks() в глобальный массив $arIb
 */
function getIblocks() {
    global $arIb;

    $obCache = \Bitrix\Main\Data\Cache::createInstance();
    $cacheTime = 86400;
    $cacheId = md5('s1');
    $cachePath = "iblocks";

    if($obCache->initCache($cacheTime, $cacheId, $cachePath)) {
        $vars = $obCache->getVars();
        $arIb = $vars["arIb"];
    } elseif($obCache->startDataCache()) {
        $arIb = _getIblocks();
        $obCache->endDataCache(array("arIb" => $arIb));
    }
}

/**
 * Собирает массив индентификаторов инфоблоков CODE => ID
 * @return array Массив идентификаторов инфоблоков в виде array(CODE => ID)
 */
function _getIblocks() {
    $resIblocks = \CIBlock::GetList(array('ID' => 'ASC'), array());
    $arIb = array();
    while ($ar = $resIblocks->Fetch()) {
        $arIb[$ar['CODE']] = $ar['ID'];
    }
    return $arIb;
}
