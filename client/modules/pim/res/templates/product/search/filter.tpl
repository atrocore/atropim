<div class="form-group">
    <a href="javascript:" class="remove-attribute-filter pull-right" data-name="{{name}}">{{#unless notRemovable}}<svg class="icon"><use href="client/img/icons/icons.svg#close"></use></svg>{{/unless}}</a>
    {{#if isPinEnabled}}
        <a href="javascript:" class="pull-right pin-filter {{#if pinned}}pinned{{/if}}" data-action="pinFilter"><svg class="icon"><use href="client/img/icons/icons.svg#thumb-tack"></use></svg></a>
    {{/if}}
    <label class="control-label small" data-name="{{name}}">{{label}}</label>
    <div class="field" data-name="{{clearedName}}">{{{field}}}</div>
</div>