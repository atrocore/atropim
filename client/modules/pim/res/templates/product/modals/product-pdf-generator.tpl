<div class="row">
    <div class="col-xs-6 cell form-group">
        <label class="control-label" data-name="action"><span class="label-text">{{translate 'action' scope='Global' category='labels'}}</span></label>
        <div class="field" data-name="action">{{{action}}}</div>
    </div>
</div>
<div class="row">
    <div class="col-xs-6 cell form-group">
        <label class="control-label" data-name="template"><span class="label-text">{{translate 'template' scope='Global' category='labels'}}</span></label>
        <div class="field" data-name="template">{{{template}}}</div>
    </div>
    <div class="col-xs-6 cell form-group">
        <label class="control-label" data-name="fileName"><span class="label-text">{{translate 'fileName' scope='Global' category='labels'}}</span></label>
        <div class="field" data-name="fileName">{{{fileName}}}</div>
    </div>
</div>
<div class="row">
    <div class="col-xs-6 cell form-group">
        <label class="control-label" data-name="channel"><span class="label-text">{{translate 'channel' scope='Product' category='labels'}}</span></label>
        <div class="field" data-name="channel">{{{channel}}}</div>
    </div>
    <div class="col-xs-6 cell form-group">
        <label class="control-label" data-name="locale"><span class="label-text">{{translate 'locale' scope='Global' category='labels'}}</span></label>
        <div class="field" data-name="locale">{{{locale}}}</div>
    </div>
</div>
{{#if isEnabledFiles}}
    <div class="row">
        <div class="col-xs-6 cell form-group">
            <label class="control-label" data-name="linkAs"><span class="label-text">{{translate 'linkAs' scope='PdfGenerator' category='labels'}}</span></label>
            <div class="field" data-name="linkAs">{{{saveAsFile}}}</div>
        </div>
        <div class="col-xs-6 cell form-group">
            <label class="control-label" data-name="saveIn"><span class="label-text">{{translate 'saveIn' scope='PdfGenerator' category='labels'}}</span></label>
            <div class="field" data-name="saveIn">{{{saveIn}}}</div>
        </div>
    </div>
    <div class="row">
        <div class="col-xs-6 cell form-group">
            <label class="control-label" data-name="folder"><span class="label-text">{{translate 'Folder' category='scopeNames'}}</span></label>
            <div class="field" data-name="folder">{{{folder}}}</div>
        </div>
        <div class="col-xs-6 cell form-group">
            <label class="control-label" data-name="fileType"><span class="label-text">{{translate 'FileType' category='scopeNames'}}</span></label>
            <div class="field" data-name="fileType">{{{fileType}}}</div>
        </div>
    </div>
    <div class="row">
        <div class="col-xs-6 cell form-group">
            <label class="control-label" data-name="replacePdf"><span class="label-text">{{translate 'replacePdf' scope='PdfGenerator' category='labels'}}</span></label>
            <div class="field" data-name="replacePdf">{{{replacePdf}}}</div>
        </div>
    </div>
{{/if}}