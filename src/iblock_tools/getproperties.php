<?
function getProperties($type = "L")
{
    $obCache = \Bitrix\Main\Data\Cache::createInstance();
    $cacheTime = 86400;
    $cacheId = md5($type);
    $cachePath = "properties";

    $arProperties = array();

    if($obCache->initCache($cacheTime, $cacheId, $cachePath)) {
        $vars = $obCache->getVars();
        $arProperties = $vars["arProperties"];
    } elseif($obCache->startDataCache()) {
        $arProperties = _getProperties($type);
        $obCache->endDataCache(array("arProperties" => $arProperties));
    }

    return $arProperties;
}

function _getProperties($type = "L")
{
    $resProperties = \CIBlockProperty::GetList(array(), array("PROPERTY_TYPE" => $type));

    $arProperties = array();
    while($arProperty = $resProperties->Fetch()) {
        $arProperties[$arProperty["CODE"]] = $arProperty;
    }

    $resPropValues = \CIBlockPropertyEnum::GetList(array('SORT'=>'ASC', 'VALUE'=>'ASC'), array("CODE" => array_keys($arProperties)));

    while($arPropValue = $resPropValues->Fetch()) {
        $arProperties[$arPropValue["PROPERTY_CODE"]]["VALUES"][$arPropValue["XML_ID"]] = $arPropValue["ID"];
    }

    return $arProperties;
}

function getPropertiesEx($arParams)
{
    $obCache = \Bitrix\Main\Data\Cache::createInstance();
    $cacheTime = 86400;
    $cacheId = md5($arParams);
    $cachePath = "properties";

    $arProperties = array();

    if($obCache->initCache($cacheTime, $cacheId, $cachePath)) {
        $vars = $obCache->getVars();
        $arProperties = $vars["arProperties"];
    } elseif($obCache->startDataCache()) {
        $arProperties = _getPropertiesEx($arParams);
        $obCache->endDataCache(array("arProperties" => $arProperties));
    }

    return $arProperties;
}

function _getPropertiesEx($arParams)
{
    $arFilter = array();
    $arProperties = array();
    $arPropListIds = array();

    if(notEmpty($arParams["type"])) {
        $arFilter["PROPERTY_TYPE"] = $arParams["type"];
    }

    if(notEmpty($arParams["iblock_id"])) {
        $arFilter["IBLOCK_ID"] = iblock($arParams["iblock_id"]);
    }

    $resProperties = \CIBlockProperty::GetList(array(), $arFilter);

    while($arProperty = $resProperties->Fetch()) {
        $arProperties[$arProperty["CODE"]] = $arProperty;
        if($arProperty["PROPERTY_TYPE"] == "L") {
            $arPropListIds[] = $arProperty["ID"];
        }
    }

    // В фильтр CIBlockPropertyEnum::GetList() нельзя передать
    // PROPERTY_ID в виде массива ID, поэтому перебираем
    // каждое свойство в цикле
    foreach ($arPropListIds as $propListId) {
        $resPropValues = \CIBlockPropertyEnum::GetList(
            array('SORT'=>'ASC', 'VALUE'=>'ASC'),
            array('PROPERTY_ID' => $propListId)
        );
        while($arPropValue = $resPropValues->Fetch()) {
            $arProperties[$arPropValue["PROPERTY_CODE"]]["VALUES"][$arPropValue["XML_ID"]] = $arPropValue["ID"];
        }
    }

    return $arProperties;
}
