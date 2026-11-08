<?php Block::put('breadcrumb') ?>
    <ul>
        <li><a href="<?= Backend::url('mercator/advent/calendars') ?>">Advent calendars</a></li>
        <li><?= e($this->pageTitle) ?></li>
    </ul>
<?php Block::endPut() ?>

<?php if (!$this->fatalError): ?>
    <?= Form::open(['class' => 'layout']) ?>
        <div class="layout-row">
            <?= $this->formRender() ?>
        </div>
        <div class="form-buttons">
            <div class="loading-indicator-container">
                <button type="submit" data-request="onSave" data-request-data="redirect:0" data-hotkey="ctrl+s, cmd+s"
                    data-load-indicator="Saving..." class="btn btn-primary">Save</button>
                <button type="button" data-request="onSave" data-request-data="close:1" data-hotkey="ctrl+enter, cmd+enter"
                    data-load-indicator="Saving..." class="btn btn-default">Save and close</button>
                <span class="btn-text">or <a href="<?= Backend::url('mercator/advent/calendars') ?>">Cancel</a></span>
            </div>
        </div>
    <?= Form::close() ?>
<?php else: ?>
    <p class="flash-message static error"><?= e($this->fatalError) ?></p>
<?php endif ?>
