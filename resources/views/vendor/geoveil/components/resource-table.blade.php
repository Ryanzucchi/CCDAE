@props(['resource', 'items', 'page' => 'index', 'layer' => null])
@php
    $columns = $resource->getColumns();
    $actions = array_map(fn ($action) => $action->jsonSerialize(), $resource->getActions($page));
    $bulkActions = array_map(fn ($action) => $action->jsonSerialize(), $resource->getBulkActions($page));
    $tableId = 'gv-table-'.$resource->id.'-'.$page;
    $columnCount = count($columns) + (int) ($bulkActions !== []) + (int) ($actions !== []);
@endphp
<section class="gv-resource-table" data-geoveil-resource-table data-geoveil-resource-layer="{{ $layer ?? $resource->id }}">
    @if($bulkActions)
        <div class="gv-table-bulk-toolbar" data-geoveil-bulk-actions data-table="{{ $tableId }}">
            <span data-geoveil-selection-count role="status">0 selecionados</span>
            @foreach($bulkActions as $action)
                <form method="POST" action="{{ $action['url'] }}" data-geoveil-bulk-form data-confirm="{{ $action['confirmation'] ?? '' }}">
                    @csrf
                    @if($action['method'] !== 'POST') @method($action['method']) @endif
                    <button type="submit" disabled>{{ $action['label'] }}</button>
                </form>
            @endforeach
        </div>
    @endif
    <div class="gv-resource-table-scroll">
        <table id="{{ $tableId }}" data-geoveil-table>
            <thead><tr>
                @if($bulkActions)<th scope="col"><input type="checkbox" data-geoveil-select-all aria-label="Selecionar todos os registros"></th>@endif
                @foreach($columns as $field => $label)<th scope="col">{{ $label }}</th>@endforeach
                @if($actions)<th scope="col">Ações</th>@endif
            </tr></thead>
            <tbody>
                @forelse($items as $item)
                    <tr>
                        @if($bulkActions)<td><input type="checkbox" value="{{ data_get($item, $resource->getKeyField()) }}" data-geoveil-row-select aria-label="Selecionar registro #{{ data_get($item, $resource->getKeyField()) }}"></td>@endif
                        @foreach($columns as $field => $label)<td>{{ data_get($item, $field) ?? '—' }}</td>@endforeach
                        @if($actions)
                            <td class="gv-resource-row-actions">
                                @foreach($actions as $action)
                                    @php($url = str_replace('{id}', rawurlencode((string) data_get($item, $resource->getKeyField())), $action['url']))
                                    @if($action['method'] === 'GET')
                                        <a href="{{ $url }}" @if($action['confirmation']) data-geoveil-confirm="{{ $action['confirmation'] }}" @endif>{{ $action['label'] }}</a>
                                    @else
                                        <form method="POST" action="{{ $url }}" @if($action['confirmation']) data-geoveil-confirm="{{ $action['confirmation'] }}" @endif>
                                            @csrf
                                            @method($action['method'])
                                            <button type="submit">{{ $action['label'] }}</button>
                                        </form>
                                    @endif
                                @endforeach
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ max(1, $columnCount) }}">Nenhum registro encontrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
