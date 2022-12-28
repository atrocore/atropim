<div class="form-group">
    {{#if isPinEnabled}}
        <a href="javascript:" class="pull-right" data-action="pinFilter"><i class="fas fa-thumbtack" style="margin-left: 5px"></i></a>
    {{/if}}
    <a href="javascript:" class="remove-attribute-filter pull-right" data-name="{{name}}">{{#unless notRemovable}}<i class="fas fa-times"></i>{{/unless}}</a>
    <label class="control-label small" data-name="{{name}}">{{label}}</label>
    <div class="field" data-name="{{clearedName}}">{{{field}}}</div>
</div>