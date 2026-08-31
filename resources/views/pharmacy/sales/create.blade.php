@extends('layouts.app')

@section('title', 'Record Sale')

@section('content')
<div class="container mx-auto max-w-6xl px-4 py-8">
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Record Sale</h1>
            <p class="mt-1 text-sm text-gray-600">Record sold quantities only. Eligible stock is deducted automatically in FEFO order.</p>
        </div>
        <x-back-button :href="route('pharmacy.dashboard')" label="Back to Pharmacy Dashboard" />
    </div>

    @if(session('success'))
        <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="status">
            {{ session('success') }}
        </div>
    @endif

    <div class="mb-6 grid grid-cols-1 gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:grid-cols-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Sale Reference</p>
            <p class="mt-1 break-all text-base font-medium text-gray-900">{{ $saleReference }}</p>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Server Date &amp; Time</p>
            <p class="mt-1 text-base font-medium text-gray-900">{{ $serverTimestamp->format('M d, Y g:i A') }}</p>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Authenticated Staff</p>
            <p class="mt-1 text-base font-medium text-gray-900">{{ $staff->name }}</p>
        </div>
    </div>

    @if($inventory->isEmpty())
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-6 text-amber-900">
            <h2 class="font-semibold">No medicines are currently available to sell.</h2>
            <p class="mt-1 text-sm">Receive a non-expired stock batch before recording a sale.</p>
            <a href="{{ route('pharmacy.receiving.create') }}" class="mt-4 inline-flex rounded-lg bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800">Add Stock</a>
        </div>
    @else
        @php
            $saleRows = old('items', [['inventory_item_id' => '', 'quantity' => 1]]);
            $saleRows = is_array($saleRows) && $saleRows !== [] ? array_values($saleRows) : [['inventory_item_id' => '', 'quantity' => 1]];
            $medicineLabels = $inventory->mapWithKeys(static function ($item): array {
                $identityParts = array_values(array_filter([
                    trim((string) $item->medicine->medicine_name),
                    trim((string) $item->medicine->brand_name),
                    trim((string) $item->medicine->dosage),
                ], static fn (string $value): bool => $value !== ''));

                return [
                    (string) $item->id => implode(' — ', $identityParts).' (Available: '.(int) $item->available_stock.')',
                ];
            });
        @endphp

        <form method="POST" action="{{ route('pharmacy.sales.store') }}" class="space-y-6" novalidate>
            @csrf

            @if($errors->has('items'))
                <p class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first('items') }}</p>
            @endif

            <div class="space-y-4" data-sale-rows>
                @foreach($saleRows as $index => $row)
                    @php
                        $medicineErrorKey = "items.{$index}.inventory_item_id";
                        $selectedInventoryItemId = (string) ($row['inventory_item_id'] ?? '');
                        $selectedMedicineLabel = (string) $medicineLabels->get($selectedInventoryItemId, '');
                    @endphp
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm" data-sale-row>
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <h2 class="font-semibold text-gray-800">Medicine <span data-row-number>{{ $index + 1 }}</span></h2>
                            <button type="button" class="text-sm font-medium text-red-600 hover:text-red-800 disabled:cursor-not-allowed disabled:opacity-40" data-remove-row>Remove</button>
                        </div>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-[minmax(0,1fr)_12rem]">
                            <div data-medicine-combobox>
                                <label id="items_{{ $index }}_inventory_item_label" for="items_{{ $index }}_inventory_item_id" class="mb-1 block text-sm font-medium text-gray-700" data-medicine-combobox-label>Medicine</label>
                                <select id="items_{{ $index }}_inventory_item_id" name="items[{{ $index }}][inventory_item_id]" aria-labelledby="items_{{ $index }}_inventory_item_label" class="w-full rounded-lg border px-3 py-2 text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 @error($medicineErrorKey) border-red-500 @else border-gray-300 @enderror" data-medicine-select @error($medicineErrorKey) aria-invalid="true" aria-describedby="items_{{ $index }}_inventory_item_error" @enderror required>
                                    <option value="">Select a medicine</option>
                                    @foreach($inventory as $item)
                                        <option value="{{ $item->id }}" @selected($selectedMedicineLabel !== '' && $selectedInventoryItemId === (string) $item->id)>{{ $medicineLabels->get((string) $item->id) }}</option>
                                    @endforeach
                                </select>

                                <div data-medicine-combobox-ui hidden>
                                    <div class="relative">
                                        <input id="items_{{ $index }}_inventory_item_search" type="text" value="{{ $selectedMedicineLabel }}" role="combobox" aria-autocomplete="list" aria-haspopup="listbox" aria-expanded="false" aria-controls="items_{{ $index }}_inventory_item_listbox" aria-activedescendant="" aria-labelledby="items_{{ $index }}_inventory_item_label" aria-required="true" autocomplete="off" spellcheck="false" class="w-full rounded-lg border py-2 pl-3 pr-10 text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 @error($medicineErrorKey) border-red-500 @else border-gray-300 @enderror" data-medicine-combobox-input @error($medicineErrorKey) aria-invalid="true" aria-describedby="items_{{ $index }}_inventory_item_error" @enderror>
                                        <button type="button" class="absolute inset-y-0 right-0 flex items-center rounded-r-lg px-3 text-gray-500 hover:text-gray-800 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500" aria-label="Show medicine options" aria-controls="items_{{ $index }}_inventory_item_listbox" aria-expanded="false" data-medicine-combobox-toggle>
                                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </div>
                                    <div id="items_{{ $index }}_inventory_item_listbox" role="listbox" aria-labelledby="items_{{ $index }}_inventory_item_label" class="relative z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-gray-300 bg-white py-1 shadow-lg" data-medicine-listbox hidden>
                                        @foreach($inventory as $item)
                                            <div id="items_{{ $index }}_inventory_item_option_{{ $item->id }}" role="option" aria-selected="{{ $selectedMedicineLabel !== '' && $selectedInventoryItemId === (string) $item->id ? 'true' : 'false' }}" tabindex="-1" class="cursor-pointer px-3 py-2 text-sm text-gray-900 hover:bg-blue-50" data-medicine-option data-value="{{ $item->id }}" data-label="{{ $medicineLabels->get((string) $item->id) }}" data-search="{{ $medicineLabels->get((string) $item->id) }}">
                                                {{ $medicineLabels->get((string) $item->id) }}
                                            </div>
                                        @endforeach
                                        <div role="option" aria-disabled="true" class="px-3 py-3 text-sm text-gray-500" data-medicine-no-results hidden>No medicines match your search.</div>
                                    </div>
                                </div>
                                @error($medicineErrorKey)<p id="items_{{ $index }}_inventory_item_error" class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="items_{{ $index }}_quantity" class="mb-1 block text-sm font-medium text-gray-700">Quantity Sold</label>
                                <input id="items_{{ $index }}_quantity" name="items[{{ $index }}][quantity]" type="number" min="1" step="1" inputmode="numeric" value="{{ $row['quantity'] ?? 1 }}" class="w-full rounded-lg border px-3 py-2 text-gray-900 focus:border-blue-500 focus:ring-blue-500 @error("items.{$index}.quantity") border-red-500 @else border-gray-300 @enderror" required>
                                @error("items.{$index}.quantity")<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <button type="button" class="inline-flex w-full items-center justify-center rounded-lg border border-blue-600 px-4 py-3 text-sm font-semibold text-blue-700 hover:bg-blue-50 min-h-11 sm:w-auto sm:justify-start sm:py-2" data-add-row>
                <i class="fas fa-plus mr-2" aria-hidden="true"></i>Add Another Medicine
            </button>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <label for="notes" class="mb-1 block text-sm font-medium text-gray-700">Notes <span class="font-normal text-gray-500">(optional)</span></label>
                <textarea id="notes" name="notes" rows="3" maxlength="1000" class="w-full rounded-lg border px-3 py-2 text-gray-900 focus:border-blue-500 focus:ring-blue-500 @error('notes') border-red-500 @else border-gray-300 @enderror" placeholder="Reason or internal note for this stock deduction">{{ old('notes') }}</textarea>
                @error('notes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a href="{{ route('pharmacy.dashboard') }}" class="w-full sm:w-auto inline-flex items-center justify-center rounded-lg border border-gray-300 px-5 py-3 sm:py-2.5 text-center text-sm font-semibold text-gray-700 hover:bg-gray-50 min-h-11">Cancel</a>
                <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center rounded-lg bg-blue-700 px-5 py-3 sm:py-2.5 text-sm font-semibold text-white hover:bg-blue-800 min-h-11">Record Sale</button>
            </div>
        </form>

        <template id="sale-row-template">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm" data-sale-row>
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="font-semibold text-gray-800">Medicine <span data-row-number>__NUMBER__</span></h2>
                    <button type="button" class="text-sm font-medium text-red-600 hover:text-red-800" data-remove-row>Remove</button>
                </div>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-[minmax(0,1fr)_12rem]">
                    <div data-medicine-combobox>
                        <label id="items___INDEX___inventory_item_label" for="items___INDEX___inventory_item_id" class="mb-1 block text-sm font-medium text-gray-700" data-medicine-combobox-label>Medicine</label>
                        <select id="items___INDEX___inventory_item_id" name="items[__INDEX__][inventory_item_id]" aria-labelledby="items___INDEX___inventory_item_label" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500" data-medicine-select required>
                            <option value="">Select a medicine</option>
                            @foreach($inventory as $item)
                                <option value="{{ $item->id }}">{{ $medicineLabels->get((string) $item->id) }}</option>
                            @endforeach
                        </select>

                        <div data-medicine-combobox-ui hidden>
                            <div class="relative">
                                <input id="items___INDEX___inventory_item_search" type="text" value="" role="combobox" aria-autocomplete="list" aria-haspopup="listbox" aria-expanded="false" aria-controls="items___INDEX___inventory_item_listbox" aria-activedescendant="" aria-labelledby="items___INDEX___inventory_item_label" aria-required="true" autocomplete="off" spellcheck="false" class="w-full rounded-lg border border-gray-300 py-2 pl-3 pr-10 text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500" data-medicine-combobox-input>
                                <button type="button" class="absolute inset-y-0 right-0 flex items-center rounded-r-lg px-3 text-gray-500 hover:text-gray-800 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500" aria-label="Show medicine options" aria-controls="items___INDEX___inventory_item_listbox" aria-expanded="false" data-medicine-combobox-toggle>
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                            <div id="items___INDEX___inventory_item_listbox" role="listbox" aria-labelledby="items___INDEX___inventory_item_label" class="relative z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-gray-300 bg-white py-1 shadow-lg" data-medicine-listbox hidden>
                                @foreach($inventory as $item)
                                    <div id="items___INDEX___inventory_item_option_{{ $item->id }}" role="option" aria-selected="false" tabindex="-1" class="cursor-pointer px-3 py-2 text-sm text-gray-900 hover:bg-blue-50" data-medicine-option data-value="{{ $item->id }}" data-label="{{ $medicineLabels->get((string) $item->id) }}" data-search="{{ $medicineLabels->get((string) $item->id) }}">
                                        {{ $medicineLabels->get((string) $item->id) }}
                                    </div>
                                @endforeach
                                <div role="option" aria-disabled="true" class="px-3 py-3 text-sm text-gray-500" data-medicine-no-results hidden>No medicines match your search.</div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label for="items___INDEX___quantity" class="mb-1 block text-sm font-medium text-gray-700">Quantity Sold</label>
                        <input id="items___INDEX___quantity" name="items[__INDEX__][quantity]" type="number" min="1" step="1" inputmode="numeric" value="1" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-gray-900 focus:border-blue-500 focus:ring-blue-500" required>
                    </div>
                </div>
            </div>
        </template>
    @endif
</div>

@if($inventory->isNotEmpty())
<script>
document.addEventListener('DOMContentLoaded', () => {
    const rows = document.querySelector('[data-sale-rows]');
    const addButton = document.querySelector('[data-add-row]');
    const template = document.querySelector('#sale-row-template');
    const comboboxControllers = new WeakMap();
    let nextIndex = rows.querySelectorAll('[data-sale-row]').length;

    const normalizeSearchText = (value) => String(value ?? '').trim().toLocaleLowerCase();

    const initializeMedicineCombobox = (combobox) => {
        if (!combobox || comboboxControllers.has(combobox)) {
            return;
        }

        const label = combobox.querySelector('[data-medicine-combobox-label]');
        const select = combobox.querySelector('[data-medicine-select]');
        const ui = combobox.querySelector('[data-medicine-combobox-ui]');
        const input = combobox.querySelector('[data-medicine-combobox-input]');
        const toggle = combobox.querySelector('[data-medicine-combobox-toggle]');
        const listbox = combobox.querySelector('[data-medicine-listbox]');
        const noResults = combobox.querySelector('[data-medicine-no-results]');
        const options = [...combobox.querySelectorAll('[data-medicine-option]')];
        let activeOption = null;

        if (!label || !select || !ui || !input || !toggle || !listbox || !noResults) {
            return;
        }

        const visibleOptions = () => options.filter((option) => !option.hidden);

        const setActiveOption = (option, scroll = false) => {
            options.forEach((candidate) => {
                const isActive = candidate === option;
                candidate.classList.toggle('bg-blue-100', isActive);
                candidate.classList.toggle('font-medium', isActive);
            });

            activeOption = option;
            input.setAttribute('aria-activedescendant', option?.id ?? '');

            if (scroll && option) {
                option.scrollIntoView({ block: 'nearest' });
            }
        };

        const updateSelectedOption = () => {
            options.forEach((option) => {
                option.setAttribute('aria-selected', option.dataset.value === select.value ? 'true' : 'false');
            });
        };

        const filterOptions = () => {
            const query = normalizeSearchText(input.value);
            const matches = options.filter((option) => {
                const matchesQuery = query === '' || normalizeSearchText(option.dataset.search).includes(query);
                option.hidden = !matchesQuery;

                return matchesQuery;
            });

            noResults.hidden = matches.length !== 0;
            if (activeOption?.hidden) {
                setActiveOption(null);
            }

            return matches;
        };

        const closeListbox = () => {
            listbox.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-expanded', 'false');
            setActiveOption(null);
        };

        const openListbox = () => {
            rows.querySelectorAll('[data-medicine-combobox]').forEach((otherCombobox) => {
                if (otherCombobox !== combobox) {
                    comboboxControllers.get(otherCombobox)?.close();
                }
            });

            const matches = filterOptions();
            listbox.hidden = false;
            input.setAttribute('aria-expanded', 'true');
            toggle.setAttribute('aria-expanded', 'true');
            const selectedOption = matches.find((option) => option.dataset.value === select.value);
            setActiveOption(selectedOption ?? matches[0] ?? null);
        };

        const selectOption = (option) => {
            select.value = option.dataset.value;
            input.value = option.dataset.label;
            input.dataset.selectedLabel = option.dataset.label;
            updateSelectedOption();
            select.dispatchEvent(new Event('change', { bubbles: true }));
            input.focus({ preventScroll: true });
            closeListbox();
        };

        const initiallySelected = options.find((option) => option.dataset.value === select.value);
        input.value = initiallySelected?.dataset.label ?? '';
        input.dataset.selectedLabel = input.value;
        updateSelectedOption();

        const controller = { close: closeListbox };
        comboboxControllers.set(combobox, controller);
        combobox.dataset.comboboxInitialized = 'true';
        select.hidden = true;
        select.setAttribute('aria-hidden', 'true');
        select.tabIndex = -1;
        ui.hidden = false;
        label.htmlFor = input.id;

        input.addEventListener('focus', openListbox);
        input.addEventListener('click', () => {
            if (listbox.hidden) {
                openListbox();
            }
        });
        input.addEventListener('input', () => {
            if (select.value !== '' && input.value !== input.dataset.selectedLabel) {
                select.value = '';
                input.dataset.selectedLabel = '';
                updateSelectedOption();
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }

            openListbox();
        });
        input.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                if (listbox.hidden) {
                    openListbox();
                }

                const matches = visibleOptions();
                if (matches.length === 0) {
                    return;
                }

                const currentIndex = matches.indexOf(activeOption);
                const direction = event.key === 'ArrowDown' ? 1 : -1;
                const nextOptionIndex = currentIndex === -1
                    ? (direction === 1 ? 0 : matches.length - 1)
                    : (currentIndex + direction + matches.length) % matches.length;
                setActiveOption(matches[nextOptionIndex], true);

                return;
            }

            if (event.key === 'Enter' && !listbox.hidden) {
                event.preventDefault();
                if (activeOption) {
                    selectOption(activeOption);
                }

                return;
            }

            if (event.key === 'Escape' && !listbox.hidden) {
                event.preventDefault();
                closeListbox();
            }
        });
        input.addEventListener('blur', () => {
            window.setTimeout(() => {
                if (!combobox.contains(document.activeElement)) {
                    closeListbox();
                }
            }, 0);
        });

        toggle.addEventListener('click', () => {
            if (listbox.hidden) {
                input.focus();
                if (listbox.hidden) {
                    openListbox();
                }
            } else {
                closeListbox();
            }
        });

        listbox.addEventListener('mousedown', (event) => event.preventDefault());
        listbox.addEventListener('click', (event) => {
            const option = event.target.closest('[data-medicine-option]');
            if (option && !option.hidden) {
                selectOption(option);
            }
        });
    };

    const refreshRows = () => {
        const currentRows = [...rows.querySelectorAll('[data-sale-row]')];
        currentRows.forEach((row, index) => {
            row.querySelector('[data-row-number]').textContent = index + 1;
            const removeButton = row.querySelector('[data-remove-row]');
            removeButton.disabled = currentRows.length === 1;
            removeButton.setAttribute('aria-label', `Remove medicine ${index + 1}`);
        });
    };

    rows.querySelectorAll('[data-medicine-combobox]').forEach(initializeMedicineCombobox);

    addButton.addEventListener('click', () => {
        const html = template.innerHTML
            .replaceAll('__INDEX__', String(nextIndex))
            .replaceAll('__NUMBER__', String(nextIndex + 1));
        rows.insertAdjacentHTML('beforeend', html);
        const addedRow = rows.lastElementChild;
        nextIndex += 1;
        initializeMedicineCombobox(addedRow.querySelector('[data-medicine-combobox]'));
        refreshRows();
        addedRow.querySelector('[data-medicine-combobox-input]').focus();
    });

    rows.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-remove-row]');
        if (!removeButton || removeButton.disabled) {
            return;
        }

        removeButton.closest('[data-sale-row]').remove();
        refreshRows();
    });

    document.addEventListener('click', (event) => {
        rows.querySelectorAll('[data-medicine-combobox]').forEach((combobox) => {
            if (!combobox.contains(event.target)) {
                comboboxControllers.get(combobox)?.close();
            }
        });
    });

    refreshRows();
});
</script>
@endif
@endsection
