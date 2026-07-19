<?php
/**
 * AGROVISE - Shared "Policy Calculator" grid.
 *
 * The calculator is how a policy gets its price: pick one or more products,
 * set the quantity and the unit price for each, and the sum of the lines
 * becomes the policy total. Used by add-policy.php, edit-policy.php and the
 * standalone policy-calculator.php, so all three compute identically.
 *
 * Expects, before the include:
 *   $productsList — rows from `products` (id, name, category, avg_packs_per_carton)
 *   $items        — prefill lines: product_id, quantity, price, sales_tax, packs_per_carton
 */
$items = $items ?? [];
?>
<style>
    .pc-table .form-input, .pc-table .form-select { margin: 0; }
    .pc-total-bar {
        display: flex; justify-content: flex-end; align-items: baseline; gap: 40px;
        border-top: 1px solid rgba(15, 26, 14, 0.14);
        margin-top: 20px; padding-top: 20px;
    }
    .pc-total-bar .sum-block { text-align: right; }
    .pc-total-bar .sum-block span {
        display: block; font-size: 0.6rem; font-weight: 500;
        letter-spacing: 0.24em; text-transform: uppercase; color: #8a8f83; margin-bottom: 5px;
    }
    .pc-total-bar .sum-block strong {
        font-family: 'Cormorant Garamond', Georgia, serif;
        font-size: 1.35rem; color: #23291f; font-variant-numeric: tabular-nums;
    }
    .pc-total-bar .sum-block.grand strong { font-size: 2rem; color: #0f1a0e; }
    .pc-total-bar .sum-block.grand strong::before { content: 'Rs. '; font-size: 1rem; color: #c2a04f; }
    .pc-empty td { text-align: center; color: #8a8f83; padding: 28px 10px; font-size: 0.86rem; }
</style>

<table class="data-table pc-table" id="pcTable">
    <thead>
        <tr>
            <th style="width: 34%;">Product</th>
            <th>Packs/Carton</th>
            <th>Quantity</th>
            <th>Unit Price (Rs)</th>
            <th>Tax % <small style="font-weight:400; color:#999;">(optional)</small></th>
            <th>Line Total (Rs)</th>
            <th></th>
        </tr>
    </thead>
    <tbody id="pcBody">
        <tr class="pc-empty" id="pcEmptyRow"><td colspan="7">No products yet &mdash; add a line to start building the policy.</td></tr>
    </tbody>
</table>

<div style="margin-top: 15px;">
    <button type="button" class="btn btn-secondary btn-sm" onclick="addPolicyRow()"><i class="fas fa-plus"></i> Add Product</button>
</div>

<div class="pc-total-bar">
    <div class="sum-block">
        <span>Products</span>
        <strong id="pcCount">0</strong>
    </div>
    <div class="sum-block">
        <span>Subtotal</span>
        <strong id="pcSubtotal">0.00</strong>
    </div>
    <div class="sum-block">
        <span>Sales Tax</span>
        <strong id="pcTax">0.00</strong>
    </div>
    <div class="sum-block grand">
        <span>Policy Price</span>
        <strong id="pcGrand">0.00</strong>
    </div>
</div>

<script>
const pcProducts = <?php echo json_encode($productsList); ?>;
const pcPrefill  = <?php echo json_encode(array_values($items)); ?>;
let pcRowIndex = 0;

function addPolicyRow(prefill) {
    pcRowIndex++;
    const body = document.getElementById('pcBody');
    const empty = document.getElementById('pcEmptyRow');
    if (empty) empty.style.display = 'none';

    const tr = document.createElement('tr');
    tr.id = 'pc-row-' + pcRowIndex;

    const options = pcProducts.map(p =>
        `<option value="${p.id}" data-packs="${p.avg_packs_per_carton || 0}">${p.name}</option>`
    ).join('');

    tr.innerHTML = `
        <td>
            <select name="product_id[]" class="form-select pc-product" required onchange="productPicked(this)">
                <option value="">-- Choose Product --</option>
                ${options}
            </select>
        </td>
        <td><input type="number" name="packs_per_carton[]" class="form-input pc-packs" min="0" style="width: 90px;"></td>
        <td><input type="number" name="quantity[]" class="form-input pc-qty" step="0.01" min="0.01" value="1" required style="width: 90px;" oninput="calcPolicy()"></td>
        <td><input type="number" name="price[]" class="form-input pc-price" step="0.01" min="0" required style="width: 110px;" oninput="calcPolicy()"></td>
        <td><input type="number" name="sales_tax[]" class="form-input pc-tax" step="0.01" min="0" max="100" placeholder="0" style="width: 80px;" oninput="calcPolicy()"></td>
        <td><strong class="pc-line">0.00</strong></td>
        <td><button type="button" class="btn-icon delete" title="Remove" onclick="removePolicyRow(this)"><i class="fas fa-trash"></i></button></td>
    `;
    body.appendChild(tr);

    if (prefill) {
        tr.querySelector('.pc-product').value = prefill.product_id;
        tr.querySelector('.pc-packs').value   = prefill.packs_per_carton ?? '';
        tr.querySelector('.pc-qty').value     = prefill.quantity;
        tr.querySelector('.pc-price').value   = prefill.price;
        tr.querySelector('.pc-tax').value     = parseFloat(prefill.sales_tax) > 0 ? prefill.sales_tax : '';
    }
    calcPolicy();
}

// Default the packs/carton from the product's catalog value (still editable)
function productPicked(select) {
    const opt = select.options[select.selectedIndex];
    const row = select.closest('tr');
    if (opt && opt.dataset.packs && !row.querySelector('.pc-packs').value) {
        row.querySelector('.pc-packs').value = opt.dataset.packs;
    }
    calcPolicy();
}

function removePolicyRow(btn) {
    btn.closest('tr').remove();
    if (!document.querySelectorAll('#pcBody tr:not(.pc-empty)').length) {
        document.getElementById('pcEmptyRow').style.display = '';
    }
    calcPolicy();
}

// The sum of the lines IS the policy price.
function calcPolicy() {
    let sub = 0, tax = 0, count = 0;
    document.querySelectorAll('#pcBody tr:not(.pc-empty)').forEach(tr => {
        const q = parseFloat(tr.querySelector('.pc-qty').value) || 0;
        const p = parseFloat(tr.querySelector('.pc-price').value) || 0;
        const t = parseFloat(tr.querySelector('.pc-tax').value) || 0;
        const base = q * p;
        const lineTax = base * (t / 100);
        tr.querySelector('.pc-line').textContent = (base + lineTax).toFixed(2);
        sub += base;
        tax += lineTax;
        count++;
    });
    const fmt = n => n.toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('pcCount').textContent    = count;
    document.getElementById('pcSubtotal').textContent = fmt(sub);
    document.getElementById('pcTax').textContent      = fmt(tax);
    document.getElementById('pcGrand').textContent    = fmt(sub + tax);
}

// Prefill (editing an existing policy, or a form that came back with errors)
if (pcPrefill.length) {
    pcPrefill.forEach(addPolicyRow);
} else {
    addPolicyRow();
}
</script>
