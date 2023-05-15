<div class="row">
    <div class="col-xs-6 cell form-group">
        <label class="control-label" data-name="template">{{translate 'template' scope='Global' category='labels'}}</label>
        <div class="field" data-name="template">{{{template}}}</div>
    </div>
</div>
<div class="row">
    <div class="col-xs-6 cell form-group">
        <label class="control-label" data-name="channel">{{translate 'channel' scope='Product' category='labels'}}</label>
        <div class="field" data-name="channel">{{{channel}}}</div>
    </div>
</div>
<div class="row">
    <div class="col-xs-6 cell form-group">
        <label class="control-label" data-name="locale">{{translate 'locale' scope='Global' category='labels'}}</label>
        <div class="field" data-name="locale">{{{locale}}}</div>
    </div>
</div>
<div class="row">
    <div class="col-xs-6 cell form-group">
        <label class="control-label" data-name="fileName">{{translate 'fileName' scope='Global' category='labels'}}</label>
        <div class="field" data-name="fileName">{{{fileName}}}</div>
    </div>
</div>
{{#if isEnabledAssets}}
    <div class="row">
        <div class="col-xs-6 cell form-group">
            <label class="control-label" data-name="saveAsAsset">{{translate 'saveAsAsset' scope='PdfGenerator' category='labels'}}</label>
            <div class="field" data-name="saveAsAsset">{{{saveAsAsset}}}</div>
        </div>
    </div>
    <div class="row">
        <div class="col-xs-6 cell form-group">
            <label class="control-label" data-name="assetCategory">{{translate 'AssetCategory' category='scopeNames'}}</label>
            <div class="field" data-name="assetCategory">{{{assetCategory}}}</div>
        </div>
        <div class="col-xs-6 cell form-group">
            <label class="control-label" data-name="assetType">{{translate 'AssetType' category='scopeNames'}}</label>
            <div class="field" data-name="assetType">{{{assetType}}}</div>
        </div>
    </div>
{{/if}}
