<?
/**
 * Надстройки над битриксовыми CIBlockElement::GetList() и CIBlockSection::GetList() с кэшированием
 *
 * @copyright 2015 Dmitry Doroshev
 */

/**
 * Обёртка, кэширующая результаты _getList()
 * @param  integer $iblockId  ID или символьный код инфоблока
 * @param  array   $arFilter  Параметры фильтра
 * @param  array   $arSort    Параметры сортировки
 * @param  array   $arSelect  Поля для выборки
 * @param  boolean $sec       <b>true</b>, если нужно выбрать секции, а не элементы
 * @param  boolean $idKey     <b>true</b>, если ключи результирующего массива
 *                            должны принять значения ID элемента:
 *                            <code>array(ID1 => array(...), ID2 => array(...))</code>
 *                            Если параметр равен <b>false</b>, массив будет неассоциативным:
 *                            <code>array(array(...), array(...))</code>
 * @param  integer $cacheTime Время хранения кэша
 * @return array              Массив с результатами выборки
 */
function getList($iblockId, $arFilter=array(), $arSort=array('SORT'=>'ASC'), $arSelect=array(), $sec=false, $idKey=true, $cacheTime=3600)
{
    $obCache = \Bitrix\Main\Data\Cache::createInstance();
    $cacheId = md5(serialize(array($iblockId, $arFilter, $arSort, $arSelect, $sec, $idKey)));
    $cachePath = '/lists/';
    $arElements = array();
    
    if($obCache->initCache($cacheTime, $cacheId, $cachePath)) {
        $vars = $obCache->getVars();
        $arElements = $vars['arElements'];
    } elseif($obCache->startDataCache()) {
        $arElements = _getList($iblockId, $arFilter, $arSort, $arSelect, $sec, $idKey);
        $obCache->endDataCache(array('arElements' => $arElements));
    }

    return $arElements;
}

/**
 * Возвращает результат работы метода GetList() в виде собранного массива
 * 
 * @param  integer $iblockId ID или символьный код инфоблока
 * @param  array   $arFilter Параметры фильтра
 * @param  array   $arSort   Параметры сортировки
 * @param  array   $arSelect Поля для выборки
 * @param  boolean $sec      *true*, если нужно выбрать секции, а не элементы
 * @param  boolean $idKey    *true*, если ключи результирующего массива
 *                           должны принять значения ID элемента:
 *                           <code>array(ID1 => array(...), ID2 => array(...))</code>
 *                           Если параметр равен <b>false</b>, массив будет неассоциативным:
 *                           <code>array(array(...), array(...))</code>
 * @return array|boolean     Массив с результатами выборки либо *false*
 */
function _getList($iblockId, $arFilter, $arSort, $arSelect, $sec, $idKey)
{
    $arParams = array(
        'iblock_id' => $iblockId,
        'filter' => $arFilter,
        'sort' => $arSort,
        'select' => $arSelect,
        'get_sections' => $sec,
        'id_key' => $idKey
    );

    return _getListEx($arParams);
}

/**
 * Обёртка, кэширующая результаты _getListEx()
 *
 * Возможные параметры $arOptions:
 * - *iblock_id* - ID или символьный код инфоблока
 * - *filter* - Параметры фильтра
 * - *sort* - Параметры сортировки
 * - *select* - Поля для выборки
 * - *get_sections* - *true*, если нужно выбрать секции, а не элементы
 * - *id_key* - *true*, если ключи результирующего массива
 *              должны принять значения ID элемента:<br>
 *              <code>array(ID1 => array(...), ID2 => array(...))</code><br>
 *              Если параметр равен *false*, массив будет неассоциативным:<br>
 *              <code>array(array(...), array(...))</code>
 * - *is_sub_query* - Если *true*, результат выборки будет оформлен для фильтрации в другом _getListEx()
 * - *cache_time* - Время хранения кэша
 * - *nav* — Параметры для постраничной навигации, формируются, как в стандартном GetList()
 *           https://dev.1c-bitrix.ru/api_help/iblock/classes/ciblockelement/getlist.php
 * 
 * @example getlistex.php Выбор всех компаний, у которых есть офис в Ярославле. Пример вложенных getListEx
 * @param  array $arOptions Параметры выборки
 * @return array            Массив с результатами выборки
 */
function getListEx($arOptions)
{
    $iblockId = iblock($arOptions['iblock_id']);
    if($iblockId === false) {
        return false;
    }

    $obCache = \Bitrix\Main\Data\Cache::createInstance();
    $cacheTime = intval($arOptions['cache_time']) > 0 ? intval($arOptions['cache_time']) : 3600;
    $cacheId = md5(serialize($arOptions));
    $cachePath = '/lists_ex/'.$iblockId.'/';
    $arElements = array();
    
    if($obCache->initCache($cacheTime, $cacheId, $cachePath)) {
        $vars = $obCache->getVars();
        $arElements = $vars['arElements'];
    } elseif($obCache->startDataCache()) {
        global $CACHE_MANAGER;
        $CACHE_MANAGER->StartTagCache($cachePath);
        
        $CACHE_MANAGER->RegisterTag('iblock_id_' . $iblockId);
        
        $arElements = _getListEx($arOptions);
        foreach ($arElements as $arElement) {
            if(isset($arElement["ID"])) {
                $CACHE_MANAGER->RegisterTag("element_".$arElement["ID"]);
            }

            if(!is_array($arElement) && intval($arElement) > 0) {
                $CACHE_MANAGER->RegisterTag("element_".intval($arElement));
            }
        }
        $CACHE_MANAGER->EndTagCache();

        $obCache->endDataCache(array('arElements' => $arElements));
    }

    return $arElements;
}

/**
 * Расширенная версия getList(), принимающая один массив параметров
 *
 * Возможные параметры $arOptions:
 * - *iblock_id* - ID или символьный код инфоблока
 * - *filter* - Параметры фильтра
 * - *sort* - Параметры сортировки
 * - *select* - Поля для выборки
 * - *get_sections* - *true*, если нужно выбрать секции, а не элементы
 * - *id_key* - *true*, если ключи результирующего массива
 *              должны принять значения ID элемента:<br>
 *              <code>array(ID1 => array(...), ID2 => array(...))</code><br>
 *              Если параметр равен *false*, массив будет неассоциативным:<br>
 *              <code>array(array(...), array(...))</code>
 * - *is_sub_query* - Если *true*, результат выборки будет оформлен для фильтрации в другом _getListEx()
 * - *nav* — Параметры для постраничной навигации, формируются, как в стандартном GetList()
 *           https://dev.1c-bitrix.ru/api_help/iblock/classes/ciblockelement/getlist.php
 * 
 * @example _getlistex.php Выбор всех компаний, у которых есть офис в Ярославле
 * @param  array $arOptions Параметры выборки
 * @return array            Массив с результатами выборки
 */
function _getListEx($arOptions)
{
    $iblockId = iblock($arOptions['iblock_id']);
    if($iblockId === false) {
        return false;
    }

    $arFilter = $arOptions['filter'] ?: array();
    $arSort = $arOptions['sort'] ?: array();
    $arSelect = $arOptions['select'] ?: array();
    $sec = $arOptions['get_sections'] ?: false;
    $idKey = $arOptions['id_key'] ?: false;
    $isSubQuery = $arOptions['is_sub_query'] ?: false;
    $arNavParams = $arOptions['nav'] ?: false;

    $arFilter = array_merge(array('IBLOCK_ID' => $iblockId), $arFilter);

    if ($sec === false) {
        $resElements = CIBlockElement::GetList($arSort, $arFilter, false, $arNavParams, $arSelect);
    } else {
        $resElements = CIBlockSection::GetList($arSort, $arFilter, false, $arSelect, $arNavParams);
    }

    if (empty($arSelect)) {
        while ($obElement = $resElements->GetNextElement()) {
            $arElement = $obElement->GetFields();
            $arElement['PROPERTIES'] = $obElement->GetProperties();
            if($idKey === true) {
                $arElements[$arElement['ID']] = $arElement;
            } else {
                $arElements[] = $arElement;
            }
        }
    } else {
        while ($arElement = $resElements->Fetch()) {
            if($isSubQuery) {
                $arElements[] = array_shift($arElement);
            } else {
                if (isset($arElement['ID']) && $idKey === true) {
                    $arElements[$arElement['ID']] = $arElement;
                } else {
                    $arElements[] = $arElement;
                }
            }
        }
    }

    if(empty($arElements)) {
        return false;
    }

    return $arElements;
}


