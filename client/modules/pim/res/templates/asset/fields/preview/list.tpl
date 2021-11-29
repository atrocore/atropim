{{#if icon}}
<a href="?entryPoint=download&id={{fileId}}" target="_blank">
<span class="fiv-cla fiv-icon-{{icon}} fiv-size-lg"></span>
</a>
{{else}}
<a data-action="showImagePreview" data-id="{{get model "id"}}" href="{{originPath}}" class="image-preview">
    <img src="{{thumbnailPath}}" style="max-width: 100px;"/>
</a>
{{#if isMainImage}}
    <div class="main-image {{#if globalMainImage}}global-main-image{{/if}}"></div>
{{/if}}
{{/if}}