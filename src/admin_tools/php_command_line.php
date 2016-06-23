<?php

$eventManager = Bitrix\Main\EventManager::getInstance();

$eventManager->addEventHandler('main', 'onAdminTabControlBegin', function () {
    $request = Bitrix\Main\Context::getCurrent()->getRequest();
    if (!$request->isPost() && $request->getRequestedPage() === '/bitrix/admin/php_command_line.php') {
        CJSCore::Init(array("jquery"));
        Bitrix\Main\Page\Asset::getInstance()->addString('
            <script type="text/javascript">
            $(function() {
                $(".bxce").keydown(function (event) {
                    if((event.metaKey || event.ctrlKey) && event.keyCode == 13) {
                        var $admBtnSave = $(".adm-btn-save");
                        $admBtnSave.focus();
                        $admBtnSave.click();
                    }
                });
            });
            </script>
        ', true);
    }
});
