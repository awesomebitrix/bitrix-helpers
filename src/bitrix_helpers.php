<?php
/**
 * Общие хелперы
 *
 * @copyright 2015 Dmitry Doroshev
 */

/**
 * Засекает время выполнения
 *
 * Корректно выполняется только внутри функции или метода,
 * потому что использует его название для хранения данных в $GLOBALS
 *
 * @param  boolean $kill Если *true*, данные о текущем таймере стираются. По умолчанию *false*
 */
function timer($kill = false) {
    if(!defined("DEBUG_TIME") || DEBUG_TIME===false) {
        return;
    }
    $trace = debug_backtrace();
    $caller = $trace[1];
    $callerName = $caller['function'];

    if (isset($caller['class'])) {
        $callerName = $caller['class'].'->'.$callerName.'()';
    }

    if($kill === true) {
        unset($GLOBALS[$callerName]);
        return;
    }

    if(isset($GLOBALS[$callerName])) {
        $time = microtime(true) - $GLOBALS[$callerName];
        AddMessage2Log(print_r($callerName.': '.$time, true));
        $GLOBALS[$callerName] = microtime(true);
    } else {
        $GLOBALS[$callerName] = microtime(true);
        AddMessage2Log(print_r($callerName.': init timer', true));
    }
}

/**
 * Проверка на заполненность значения с дополнительной проверкой на соответствие типу данных
 *
 * Возвращает *true*, если значение не пусто и соответствует указанному типу,
 * и *false, если значение пусто либо не соответствует указанному типу
 *
 * Возможные типы:
 * - 'int',
 * - 'array'.
 *
 * @param  mixed   $value     Значение для проверки
 * @param  string  $fieldType Строка с названием типа. По умолчанию пустая, проверка не производится
 * @return boolean
 */
function notEmpty($value, $fieldType='') {
    $checked = true;

    if($fieldType !== false) {
        switch ($fieldType) {
            case 'int':
                $checked = (intval($value)."" === $value) || is_int($value);
                break;
            case 'array':
                $checked = is_array($value);
                break;
            default:
                $checked = true;
                break;
        }
    }
    return (isset($value) && !empty($value) && $checked);
}

/**
 * Убирает префикс "PROPERTY_" из ключей исходного массива и возвращает обработанный массив
 *
 * @param  array $arFields Исходный массив
 * @return array
 */
function cleanPropertyFields($arFields)
{
    $arNewFields = array();

    foreach ($arFields as $fieldName => $field) {
        if(strpos($fieldName, 'PROPERTY_') === 0) {
            $arNewFields[str_replace('PROPERTY_', '', $fieldName)] = $field;
        }
    }

    if(!empty($arNewFields)) {
        return $arNewFields;
    }
    return $arFields;
}

/**
 * Добавляет значение ко множественному свойству элемента инфоблока
 *
 * **Не работает со значениями-массивами**
 *
 * @param  integer          $elementId    ID элемента инфоблока
 * @param  integer          $iblockId     ID инфоблока
 * @param  string           $propertyCode Символьный код свойства
 * @param  string|integer   $value        Добавляемое значение
 * @return boolean                        Всегда *true*
 */
function appendValue($elementId, $iblockId, $propertyCode, $value)
{
    $failed = false;

    if(!isElementOfIblock($elementId, $iblockId)) {
        $failed = true;
    }

    if(!isPropertyOfIblock($propertyCode, $iblockId)) {
        $failed = true;
    }

    if(is_array($value)) {
        $failed = true;
    }

    if(!is_string($propertyCode)) {
        $failed = true;
    }

    if($failed === true) {
        AddMessage2Log(print_r(array(
            'element_id' => $elementId,
            'iblock_id' => $iblockId,
            'property_code' => $propertyCode,
            'value' => $value
        ), true));
        return false;
    }

    $arValues = array();
    $resCurrentValues = \CIBlockElement::GetProperty(
        $iblockId, $elementId,
        'sort', 'asc',
        array("CODE" => $propertyCode)
    );
    while($arValue = $resCurrentValues->Fetch()) {
        $arValues[$arValue["VALUE"]] = 1;
    }
    $arValues[$value] = 1;

    \CIBlockElement::SetPropertyValuesEx(
        $elementId, $iblockId,
        array($propertyCode => array_keys($arValues))
    );

    return true;
}

/**
 * Удаляет значение из множественного свойства элемента инфоблока
 *
 * **Не работает со значениями-массивами**
 *
 * @param  integer          $elementId    ID элемента инфоблока
 * @param  integer          $iblockId     ID инфоблока
 * @param  string           $propertyCode Символьный код свойства
 * @param  string|integer   $value        Удаляемое значение
 * @return boolean                        Всегда *true*
 */
function removeValue($elementId, $iblockId, $propertyCode, $value)
{
    if(!isElementOfIblock($elementId, $iblockId)) {
        return false;
    }

    if(!isPropertyOfIblock($propertyCode, $iblockId)) {
        return false;
    }

    if(is_array($value)) {
        return false;
    }

    if(!is_string($propertyCode)) {
        return false;
    }

    $arValues = array();
    $resCurrentValues = \CIBlockElement::GetProperty(
        $iblockId, $elementId,
        'sort', 'asc',
        array("CODE" => $propertyCode)
    );
    while($arValue = $resCurrentValues->Fetch()) {
        $arValues[$arValue["VALUE"]] = 1;
    }

    if(isset($arValues[$value])) {
        unset($arValues[$value]);
        \CIBlockElement::SetPropertyValuesEx(
            $elementId, $iblockId,
            array($propertyCode => !empty($arValues) ? array_keys($arValues) : false)
        );
    }
    return true;
}

/**
 * Возвращает ID инфоблока по его символьному коду
 *
 * Если инфоблок не найден, возвращает *false*
 *
 * @param  string|integer $iblock Символьный код инфоблока или его ID
 * @return integer|boolean        ID инфоблока или *false*, если инфоблок не найден
 */
function iblock($iblock)
{
    global $arIb;
    if(intval($iblock) == 0) {
        if(!isset($arIb[$iblock])) {
            return false;
        }
        $iblock = $arIb[$iblock];
    }

    if(!in_array($iblock, $arIb)) {
        return false;
    }

    return $iblock;
}

/**
 * Проверяет, принадлежит ли элемент указанному инфоблоку
 *
 * @param  integer  $elementId ID элемента
 * @param  integer  $iblockId  ID инфоблока
 * @return boolean             *true* — принадлежит, *false* — не принадлежит
 */
function isElementOfIblock($elementId, $iblockId)
{
    return $iblockId === \CIBlockElement::GetIBlockByID($elementId);
}

/**
 * Проверяет, принадлежит ли свойство указанному инфоблоку
 *
 * @param  string  $propertyCode Символьный код свойства
 * @param  integer $iblockId     ID инфоблока
 * @return boolean               *true* — принадлежит, *false* — не принадлежит
 */
function isPropertyOfIblock($propertyCode, $iblockId)
{
    $resProperty = \CIBlockProperty::GetList(array(), array("IBLOCK_ID" => $iblockId, "CODE" => $propertyCode));
    if($arProperty = $resProperty->Fetch()) {
        return true;
    }
    return false;
}

function getCurrentDate($type = "FULL", $object = false)
{
    global $DB;
    $currentDate = date($DB->DateFormatToPHP(\CSite::GetDateFormat($type)), time());

    if($object === false) {
        return $currentDate;
    }

    return new \Bitrix\Main\Type\DateTime($currentDate);
}

function getIBlockCodeByElement($elementId)
{
    $arIblock = \CIBlock::GetArrayByID(\CIBlockElement::GetIBlockByID($elementId));
    if(!empty($arIblock)) {
        return $arIblock['CODE'];
    }
    return false;
}

include 'iblock_tools/getlist.php';
include 'iblock_tools/getproperties.php';
include 'iblock_tools/getiblocks.php';
