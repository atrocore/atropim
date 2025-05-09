{{#unless fields}}
    {{translate 'No fields available for Mass Update'}}
{{else}}
<div class="button-container">
    <button class="btn btn-default pull-right hidden" data-action="reset">{{translate 'Reset'}}</button>
    <div class="btn-group">
        <button class="btn btn-default dropdown-toggle select-field" data-toggle="dropdown" tabindex="-1">{{translate 'Select Field'}} <i class="ph ph-caret-down"></i></button>
        <ul class="dropdown-menu pull-left filter-list">
        {{#each fields}}
            <li data-name="{{this}}"><a href="javascript:" data-name="{{this}}" data-action="add-field">{{translate this scope=../scope category='fields'}}</a></li>
        {{/each}}
        </ul>
    </div>
    {{#if isAllowedMassUpdateAttributeValue}}
    <div class="btn-group">
        <button class="btn btn-default dropdown-toggle select-field" data-action="select-attribute" tabindex="-1">{{translate 'Select Attribute'}}</button>
    </div>
    {{/if}}
</div>
{{/unless}}
<div class="row">
    <div class="fields-container"></div>
</div>
