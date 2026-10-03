<?php
$forms = FriendsOfRedaxo\FormStudio\Form::options();
?>
<div class="form-horizontal">
    <div class="form-group">
        <label class="col-sm-2 control-label" for="fs-form-select">Formular</label>
        <div class="col-sm-10">
            <select class="form-control selectpicker" id="fs-form-select" name="REX_INPUT_VALUE[1]">
                <option value="">Bitte wählen …</option>
                <?php foreach ($forms as $id => $name): ?>
                    <option value="<?= $id ?>"<?= (string) $id === 'REX_VALUE[1]' ? ' selected' : '' ?>><?= rex_escape($name) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="help-block">Formulare gestalten unter <a href="<?= rex_url::backendPage('form_studio/forms') ?>">Form Studio → Formulare</a>.</p>
        </div>
    </div>
</div>
