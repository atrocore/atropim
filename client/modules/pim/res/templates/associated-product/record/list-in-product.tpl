{{#if collection.models.length}}

<div class="list associated-products-list">
    <table class="table full-table">
        {{#if header}}
        <thead>
            <tr>
                {{#each headerDefs}}
                <th {{#if width}} width="{{width}}"{{/if}}{{#if align}} style="text-align: {{align}};"{{/if}}>
                    <div>
                        {{#if this.sortable}}
                            <a href="javascript:" class="sort" data-name="{{this.name}}">{{#if this.hasCustomLabel}}{{this.customLabel}}{{else}}{{translate this.name scope=../../../collection.name category='fields'}}{{/if}}</a>
                            {{#if this.sorted}}{{#if this.asc}}<span>&#8593;</span>{{else}}<span>&#8595;</span>{{/if}}{{/if}}
                        {{else}}
                            {{#if this.hasCustomLabel}}
                                {{this.customLabel}}
                            {{else}}
                                {{#if this.id}}
                                    <a href="#{{../../../../groupScope}}/view/{{this.id}}">{{this.name}}</a>
                                {{else}}
                                    {{translate this.name scope=../../../../collection.name category='fields'}}
                                {{/if}}
                            {{/if}}
                        {{/if}}
                    </div>
                </th>
                {{/each}}
                {{#if editable}}
                    <th width="{{rowActionsColumnWidth}}" class="context-menu">
                        <div class="pull-right btn-group">
                            <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown">
                                <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a href="javascript:" class="action" data-action="unlinkAssociation" data-id="{{groupId}}">{{translate 'Remove'}}</a></li>
                            </ul>
                        </div>
                    </th>
                {{/if}}
            </tr>
        </thead>
        {{/if}}
        <tbody>
        {{#each rowList}}
            <tr data-id="{{./this}}" class="list-row">
            {{{var this ../this}}}
            </tr>
        {{/each}}
        </tbody>
    </table>
    {{#unless paginationEnabled}}
    {{#if showMoreEnabled}}
    <div class="show-more{{#unless showMoreActive}} hide{{/unless}}">
        <a type="button" href="javascript:" class="btn btn-default btn-block" data-action="showMore" {{#if showCount}}title="{{translate 'Total'}}: {{collection.total}}"{{/if}}>
            <span class="more-label">{{countLabel}}</span>
        </a>
        <img class="preloader" style="display:none;height:12px;margin-top: 5px" src="client/img/atro-loader.svg" />
    </div>
    {{/if}}
    {{/unless}}
</div>

{{else}}
    {{translate 'No Data'}}
{{/if}}

<style>
    .associated-products-list table th:first-child > div,
     .associated-products-list table th:first-child > div > a {
        font-weight: bold;
        color: #000000;
    }

    .associated-products-list .btn-group .btn {
        background: transparent !important;
        border: 0
    }

    .associated-products-list .context-menu {
        padding: 0;
        vertical-align: middle
    }
</style>
