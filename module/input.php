<?php
// Auch inaktive Formulare anbieten, damit eine bestehende Auswahl beim Speichern nicht verloren geht
$forms = FriendsOfRedaxo\FormStudio\Form::options(true);
?>
<div class="form-horizontal">
    <div class="form-group">
        <label class="col-sm-2 control-label" for="fs-form-select"><?= rex_i18n::msg('form_studio_module_form') ?></label>
        <div class="col-sm-10">
            <select class="form-control selectpicker" id="fs-form-select" name="REX_INPUT_VALUE[1]">
                <option value=""><?= rex_i18n::msg('form_studio_module_please_choose') ?></option>
                <?php foreach ($forms as $id => $name): ?>
                    <option value="<?= $id ?>"<?= (string) $id === 'REX_VALUE[1]' ? ' selected' : '' ?>><?= rex_escape($name) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="help-block"><?= rex_i18n::rawMsg('form_studio_module_help', rex_url::backendPage('form_studio/forms')) ?></p>
        </div>
    </div>
    <div class="form-group">
        <label class="col-sm-2 control-label" for="fs-success-text"><?= rex_i18n::msg('form_studio_module_success_text') ?></label>
        <div class="col-sm-10">
            <textarea class="form-control" id="fs-success-text" name="REX_INPUT_VALUE[2]" rows="3" aria-describedby="fs-success-text-help">REX_VALUE[2]</textarea>
            <p class="help-block" id="fs-success-text-help"><?= rex_i18n::msg('form_studio_module_success_text_help') ?></p>
        </div>
    </div>
    <div class="form-group">
        <label class="col-sm-2 control-label" for="fs-wrapper-class"><?= rex_i18n::msg('form_studio_module_wrapper_class') ?></label>
        <div class="col-sm-10">
            <input class="form-control" id="fs-wrapper-class" name="REX_INPUT_VALUE[3]" value="REX_VALUE[3]" aria-describedby="fs-wrapper-class-help" autocomplete="off" spellcheck="false">
            <p class="help-block" id="fs-wrapper-class-help"><?= rex_i18n::msg('form_studio_module_wrapper_class_help') ?></p>
        </div>
    </div>
</div>
