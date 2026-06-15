<?php
session_start();
require 'db.php';
if(!is_logged()){ header("Location: login.php"); exit; }
$uid = (int)$_SESSION['user_id'];

$user = $conn->prepare("SELECT name,email,phone,address FROM users WHERE user_id=?");
$user->bind_param("i",$uid); $user->execute();
$u = $user->get_result()->fetch_assoc();

$cs = $conn->prepare("SELECT c.quantity,c.size,c.color,c.product_id,p.name,p.price,p.sale_percent,p.image_url,
    COALESCE(ps.stock,0) AS avail_stock,
    (SELECT pi.image_url FROM product_images pi WHERE pi.product_id=c.product_id AND pi.color_name=c.color ORDER BY pi.sort_order ASC LIMIT 1) AS color_image
    FROM cart_items c
    JOIN products p ON c.product_id=p.product_id
    LEFT JOIN product_stock ps ON ps.product_id=c.product_id AND ps.color_name=c.color AND ps.size=c.size
    WHERE c.user_id=?");
$cs->bind_param("i",$uid); $cs->execute();
$cart = $cs->get_result();
if($cart->num_rows===0){ header("Location: cart.php"); exit; }

$items=[]; $subtotal=0; $has_stock_issue=false;
while($r=$cart->fetch_assoc()){
    $ep = effective_price($r['price'], (int)($r['sale_percent']??0));
    $r['eff_price'] = $ep;
    $r['sub'] = $ep * $r['quantity'];
    $subtotal += $r['sub'];
    if((int)$r['quantity'] > (int)$r['avail_stock']) $has_stock_issue = true;
    $items[] = $r;
}
$shipping = $subtotal>=300 ? 0 : 10;
$total    = $subtotal+$shipping;

// Load user's valid vouchers for hint
$my_vouchers = [];
$mv = $conn->query("SELECT code,amount,reason FROM vouchers WHERE user_id=$uid AND is_used=0 AND (expires_at IS NULL OR expires_at >= CURDATE()) LIMIT 5");
if($mv) while($vr=$mv->fetch_assoc()) $my_vouchers[] = $vr;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Checkout | Apex</title>
<link rel="stylesheet" href="css/style.css?v=10">
<style>
.bank-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:8px;}
.bank-btn{cursor:pointer;}
.bank-btn input:checked + span,.bank-btn span.sel{background:rgba(200,84,60,.12);border-color:var(--accent);color:var(--accent);}
.bank-btn span{display:block;padding:8px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:.75rem;font-weight:600;text-align:center;transition:all .18s;color:var(--text);}
.bank-btn:hover span{border-color:var(--accent);color:var(--accent);}
.pay-detail{margin-top:14px;padding:14px;background:var(--navy2);border:1px solid var(--border);border-radius:10px;animation:fadeIn .2s ease;}
@keyframes fadeIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.val-err{font-size:.75rem;color:#ef4444;margin-top:4px;display:none;}
.field-invalid input,.field-invalid textarea{border-color:#ef4444!important;}
.field-invalid .val-err{display:block;}
/* Voucher */
.voucher-row{display:flex;gap:8px;margin-top:10px;}
.voucher-input{flex:1;background:var(--navy);border:1px solid var(--border);border-radius:var(--radius);padding:9px 12px;color:var(--text);font-size:.85rem;outline:none;transition:border-color .2s;text-transform:uppercase;letter-spacing:1px;}
.voucher-input:focus{border-color:var(--accent);}
.voucher-msg{font-size:.78rem;margin-top:6px;min-height:18px;}
.voucher-msg.ok{color:#22c55e;}
.voucher-msg.err{color:#ef4444;}
.discount-row{display:flex;justify-content:space-between;padding:6px 0;font-size:.88rem;color:#22c55e;font-weight:600;border-top:1px dashed var(--border);border-bottom:1px dashed var(--border);margin:6px 0;}
</style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>
<?php if(!empty($_SESSION['checkout_error'])): ?>
<div class="wrap"><div class="flash flash-err" style="margin-top:16px;"><?=e($_SESSION['checkout_error'])?></div></div>
<?php unset($_SESSION['checkout_error']); endif; ?>

<div class="page-header" style="background:var(--navy2);">
  <div class="wrap">
    <div class="breadcrumb"><a href="index.php">Home</a><span class="sep">/</span><a href="cart.php">Cart</a><span class="sep">/</span><span>Checkout</span></div>
    <h1>CHECK<span style="color:var(--accent)">OUT</span></h1>
  </div>
</div>

<section class="section" style="padding-top:40px;">
<div class="wrap">
<form action="process_checkout.php" method="POST" id="checkoutForm" novalidate>
<?=csrf_field()?>
<!-- Hidden: payment method + detail -->
<input type="hidden" name="payment_method"   id="payMethodVal"   value="">
<input type="hidden" name="payment_detail"   id="payDetailVal"   value="">
<!-- Hidden: voucher -->
<input type="hidden" name="voucher_code"     id="voucherCodeInput"   value="">
<input type="hidden" name="discount_amount"  id="discountAmountInput" value="0">

<div class="checkout-grid">

  <!-- Left -->
  <div>
    <!-- Shipping -->
    <div class="card checkout-section">
      <h3>SHIPPING INFORMATION</h3>
      <div class="form-row">
        <div class="form-group">
          <label>Full Name</label>
          <input type="text" value="<?=e($u['name'])?>" readonly style="opacity:.6;">
        </div>
        <div class="form-group">
          <label>Phone</label>
          <input type="text" value="<?=e($u['phone'])?>" readonly style="opacity:.6;">
        </div>
      </div>
      <div class="form-group" id="addrGroup">
        <label>Shipping Address *</label>
        <textarea name="shipping_address" rows="3" id="shippingAddr"><?=e($u['address'])?></textarea>
        <span class="val-err" id="addrErr">Please enter your shipping address.</span>
      </div>
    </div>

    <!-- Payment -->
    <div class="card checkout-section">
      <h3>PAYMENT METHOD</h3>
      <p id="payMethodErr" style="font-size:.78rem;color:#ef4444;display:none;margin-bottom:8px;">Please select a payment method.</p>
      <div class="payment-options">
        <?php foreach([
          ['online_banking','','Online Banking','FPX — All major banks'],
          ['credit_card',   '','Credit / Debit','Visa, Mastercard'],
          ['ewallet',       '','E-Wallet','GrabPay, Touch n Go'],
          ['cod',           '','Cash on Delivery','Pay upon receiving'],
        ] as $pm): ?>
        <label class="pay-opt" data-method="<?=e($pm[0])?>"
               onclick="selectPayment('<?=e($pm[0])?>')">
          <div>
            <div style="font-weight:600;font-size:.875rem;"><?=e($pm[2])?></div>
            <div style="font-size:.75rem;color:var(--muted);"><?=e($pm[3])?></div>
          </div>
        </label>
        <?php endforeach; ?>
      </div>

      <!-- Online Banking detail -->
      <div id="detail_online_banking" class="pay-detail" style="display:none;">
        <p style="font-size:.78rem;color:var(--muted);margin-bottom:10px;">Select your bank: <span id="bankErr" style="color:#ef4444;display:none;">Please select a bank.</span></p>
        <div class="bank-grid">
          <?php foreach(['Maybank','CIMB Bank','RHB Bank','Hong Leong Bank','Public Bank','AmBank','Bank Islam','Bank Rakyat','BSN','Affin Bank','Alliance Bank','OCBC Bank'] as $b): ?>
          <label class="bank-btn">
            <input type="radio" name="bank_name" value="<?=e($b)?>" style="display:none;" onchange="pickBankWallet('bank_name','<?=e($b)?>',this)">
            <span><?=e($b)?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- E-Wallet detail -->
      <div id="detail_ewallet" class="pay-detail" style="display:none;">
        <p style="font-size:.78rem;color:var(--muted);margin-bottom:10px;">Select your e-wallet: <span id="walletErr" style="color:#ef4444;display:none;">Please select a wallet.</span></p>
        <div class="bank-grid">
          <?php foreach(['GrabPay','Touch \'n Go eWallet','Boost','ShopeePay','MAE by Maybank','BigPay'] as $w): ?>
          <label class="bank-btn">
            <input type="radio" name="wallet_name" value="<?=e($w)?>" style="display:none;" onchange="pickBankWallet('wallet_name','<?=e($w)?>',this)">
            <span><?=e($w)?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Credit/Debit detail -->
      <div id="detail_credit_card" class="pay-detail" style="display:none;">
        <div class="form-grid-2" style="gap:12px;">
          <div class="form-group span-2" style="margin:0;" id="grp_card_number">
            <label style="font-size:.72rem;letter-spacing:1px;color:var(--muted);">CARD NUMBER *</label>
            <input type="text" id="cardNumber" placeholder="1234  5678  9012  3456" maxlength="19" autocomplete="cc-number"
                   oninput="this.value=this.value.replace(/[^0-9]/g,'').replace(/(.{4})/g,'$1 ').trim().substr(0,19)"
                   style="background:var(--navy2);border:1px solid var(--border);border-radius:var(--radius);padding:10px 14px;color:var(--text);width:100%;font-size:.9rem;letter-spacing:2px;box-sizing:border-box;">
            <span class="val-err" id="cardNumberErr">Please enter a valid 16-digit card number.</span>
          </div>
          <div class="form-group" style="margin:0;" id="grp_card_expiry">
            <label style="font-size:.72rem;letter-spacing:1px;color:var(--muted);">EXPIRY DATE *</label>
            <input type="text" id="cardExpiry" placeholder="MM / YY" maxlength="7" autocomplete="cc-exp"
                   oninput="fmtExpiry(this)"
                   style="background:var(--navy2);border:1px solid var(--border);border-radius:var(--radius);padding:10px 14px;color:var(--text);width:100%;font-size:.9rem;box-sizing:border-box;">
            <span class="val-err" id="cardExpiryErr">Please enter a valid expiry date (MM / YY).</span>
          </div>
          <div class="form-group" style="margin:0;" id="grp_card_cvv">
            <label style="font-size:.72rem;letter-spacing:1px;color:var(--muted);">CVV *</label>
            <input type="password" id="cardCvv" placeholder="•••" maxlength="3" autocomplete="cc-csc"
                   oninput="this.value=this.value.replace(/[^0-9]/g,'')"
                   style="background:var(--navy2);border:1px solid var(--border);border-radius:var(--radius);padding:10px 14px;color:var(--text);width:100%;font-size:.9rem;box-sizing:border-box;">
            <span class="val-err" id="cardCvvErr">CVV must be 3 digits.</span>
          </div>
          <div class="form-group span-2" style="margin:0;" id="grp_card_name">
            <label style="font-size:.72rem;letter-spacing:1px;color:var(--muted);">CARDHOLDER NAME *</label>
            <input type="text" id="cardName" placeholder="Name as on card" autocomplete="cc-name"
                   style="background:var(--navy2);border:1px solid var(--border);border-radius:var(--radius);padding:10px 14px;color:var(--text);width:100%;font-size:.9rem;box-sizing:border-box;">
            <span class="val-err" id="cardNameErr">Please enter the cardholder name.</span>
          </div>
        </div>
      </div>

      <!-- COD detail -->
      <div id="detail_cod" class="pay-detail" style="display:none;">
        <div style="display:flex;align-items:center;gap:10px;padding:12px 14px;background:rgba(200,84,60,.06);border:1px solid rgba(200,84,60,.2);border-radius:8px;font-size:.82rem;color:var(--muted);">
          Pay the delivery rider in cash when your order arrives. No online payment required.
        </div>
      </div>

      <div class="sim-note" style="margin-top:14px;">Note: This is a simulated payment gateway for FYP purposes — no real money is processed.</div>
    </div>
  </div>

  <!-- Right: Order Summary -->
  <div class="card cart-summary-box" style="position:sticky;top:90px;align-self:start;">
    <div class="cs-title">ORDER SUMMARY</div>

    <?php if($has_stock_issue): ?>
    <div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:8px;padding:12px 14px;margin-bottom:12px;font-size:.8rem;color:#ef4444;line-height:1.5;">
      <strong>Stock Warning</strong> — one or more items exceeds available stock. Please
      <a href="cart.php" style="color:#ef4444;font-weight:600;text-decoration:underline;">update your cart</a> before placing the order.
    </div>
    <?php endif; ?>

    <?php foreach($items as $it):
        $over_stock = (int)$it['quantity'] > (int)$it['avail_stock'];
    ?>
    <div style="display:flex;gap:10px;align-items:center;padding:10px 0;border-bottom:1px solid var(--border);<?=$over_stock?'border-left:3px solid #ef4444;padding-left:8px;margin-left:-8px;':''?>">
      <?php $cimg = !empty($it['color_image']) ? e($it['color_image']) : (!empty($it['image_url']) ? e($it['image_url']) : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=80&q=60'); ?>
      <img src="<?=$cimg?>" style="width:52px;height:52px;border-radius:6px;object-fit:cover;flex-shrink:0;<?=$over_stock?'opacity:.6;':''?>">
      <div style="flex:1;">
        <div style="font-size:.875rem;font-weight:600;color:var(--white);"><?=e($it['name'])?></div>
        <div style="font-size:.75rem;color:var(--muted);">UK <?=e($it['size'])?> · <?=e($it['color'])?> × <?=(int)$it['quantity']?></div>
        <?php if($over_stock): ?>
        <div style="font-size:.7rem;color:#ef4444;font-weight:600;margin-top:2px;">
          Only <?=(int)$it['avail_stock']?> in stock — qty too high
        </div>
        <?php endif; ?>
        <?php $sp_pct_co = (int)($it['sale_percent']??0); if($sp_pct_co > 0): ?>
        <div style="font-size:.72rem;margin-top:2px;">
          <span style="text-decoration:line-through;color:var(--muted);">RM <?=number_format($it['price'],2)?>/pc</span>
          <span style="color:var(--danger);font-weight:600;"> RM <?=number_format($it['eff_price'],2)?>/pc</span>
          <span style="background:var(--danger);color:#fff;font-size:.6em;padding:1px 5px;border-radius:3px;font-weight:700;vertical-align:middle;"><?=$sp_pct_co?>% OFF</span>
        </div>
        <?php endif; ?>
      </div>
      <div style="font-family:'Oswald',sans-serif;color:var(--accent);">RM <?=number_format($it['sub'],2)?></div>
    </div>
    <?php endforeach; ?>

    <div class="cs-row" style="margin-top:14px;"><span>Subtotal</span><span>RM <?=number_format($subtotal,2)?></span></div>
    <div class="cs-row">
      <span>Shipping</span>
      <span><?=$shipping===0?'<span style="color:var(--accent)">FREE</span>':'RM '.number_format($shipping,2)?></span>
    </div>

    <!-- Voucher -->
    <div style="margin:14px 0 2px;border-top:1px solid var(--border);padding-top:14px;">
      <div style="font-size:.72rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted);margin-bottom:8px;">
        Voucher Code
        <?php if(!empty($my_vouchers)): ?>
        <span style="color:var(--accent);font-weight:600;"> (<?=count($my_vouchers)?> available)</span>
        <?php endif; ?>
      </div>
      <?php if(!empty($my_vouchers)): ?>
      <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px;">
        <?php foreach($my_vouchers as $mvc): ?>
        <button type="button" onclick="fillVoucher('<?=e($mvc['code'])?>')"
                style="font-size:.68rem;padding:3px 10px;border:1px dashed var(--accent);background:rgba(200,84,60,.06);color:var(--accent);border-radius:100px;cursor:pointer;font-weight:600;transition:.2s;"
                title="RM <?=number_format($mvc['amount'],2)?> off<?=!empty($mvc['reason'])?': '.$mvc['reason']:''?>">
          <?=e($mvc['code'])?>
        </button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <div class="voucher-row">
        <input type="text" id="voucherDisplay" class="voucher-input" placeholder="Enter voucher code" maxlength="20">
        <button type="button" class="btn btn-outline btn-sm" onclick="applyVoucher()" id="applyVoucherBtn">Apply</button>
        <button type="button" class="btn btn-secondary btn-sm" onclick="removeVoucher()" id="removeVoucherBtn" style="display:none;">&times; Remove</button>
      </div>
      <div id="voucherMsg" class="voucher-msg"></div>
    </div>

    <div id="discountRow" class="discount-row" style="display:none;">
      <span>Voucher Discount</span>
      <span id="discountDisplay" style="color:#22c55e;">-RM 0.00</span>
    </div>

    <div class="cs-total">
      <span>TOTAL</span>
      <span style="color:var(--accent);" id="grandTotalDisplay">RM <?=number_format($total,2)?></span>
    </div>
    <input type="hidden" name="grand_total_display" id="grandTotalHidden" value="<?=number_format($total,2)?>">
    <button type="submit" class="btn btn-primary btn-full" id="placeOrderBtn"
      <?=$has_stock_issue?'disabled title="Update your cart quantity to match available stock before ordering"':''?>>PLACE ORDER →</button>
    <?php if($has_stock_issue): ?>
    <a href="cart.php" class="btn btn-danger btn-full" style="margin-top:10px;">Update Cart to Fix Stock →</a>
    <?php else: ?>
    <a href="cart.php" class="btn btn-outline btn-full" style="margin-top:10px;">← Back to Cart</a>
    <?php endif; ?>
  </div>

</div>
</form>
</div>
</section>

<?php include 'includes/footer.php'; ?>

<script>
const _csrfToken  = '<?=csrf_token()?>';
const _baseTotal  = <?=json_encode((float)$total)?>;
let   _discountAmt = 0;
let   _voucherApplied = false;

// ── Payment Selection ─────────────────────────────────────────────
function selectPayment(method){
    document.getElementById('payMethodVal').value = method;
    document.querySelectorAll('.pay-opt').forEach(e => e.classList.remove('sel'));
    document.querySelector('[data-method="'+method+'"]').classList.add('sel');
    document.querySelectorAll('.pay-detail').forEach(e => e.style.display='none');
    const d = document.getElementById('detail_'+method);
    if(d) d.style.display='block';
    document.getElementById('payMethodErr').style.display = 'none';
    // Reset detail — COD needs no extra detail, card/bank/wallet filled later
    document.getElementById('payDetailVal').value = '';

}

// ── Bank/Wallet selection ─────────────────────────────────────────
function pickBankWallet(type, value, radio){
    const grid = radio.closest('.bank-grid');
    grid.querySelectorAll('span').forEach(s => s.classList.remove('sel'));
    radio.nextElementSibling.classList.add('sel');
    document.getElementById('payDetailVal').value = value;
    // Hide the relevant error
    const errId = type === 'bank_name' ? 'bankErr' : 'walletErr';
    document.getElementById(errId).style.display = 'none';
}

// ── Card expiry formatter ─────────────────────────────────────────
function fmtExpiry(input){
    let v = input.value.replace(/[^0-9]/g,'');
    if(v.length >= 3) v = v.substr(0,2) + ' / ' + v.substr(2,2);
    input.value = v;
}

// ── Voucher ───────────────────────────────────────────────────────
function fillVoucher(code){
    document.getElementById('voucherDisplay').value = code;
    applyVoucher();
}

function applyVoucher(){
    if(_voucherApplied) return;
    const code = document.getElementById('voucherDisplay').value.trim().toUpperCase();
    if(!code){ showVoucherMsg('Please enter a voucher code.','err'); return; }
    document.getElementById('applyVoucherBtn').disabled = true;
    document.getElementById('applyVoucherBtn').textContent = '...';
    fetch('check_voucher.php',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'csrf_token='+encodeURIComponent(_csrfToken)+'&code='+encodeURIComponent(code)
    })
    .then(r=>r.json())
    .then(data=>{
        document.getElementById('applyVoucherBtn').disabled = false;
        document.getElementById('applyVoucherBtn').textContent = 'Apply';
        if(data.valid){
            _discountAmt = parseFloat(data.amount);
            _voucherApplied = true;
            document.getElementById('voucherCodeInput').value = code;
            document.getElementById('discountAmountInput').value = _discountAmt.toFixed(2);
            showVoucherMsg(data.msg, 'ok');
            document.getElementById('discountRow').style.display = 'flex';
            document.getElementById('discountDisplay').textContent = '-RM '+_discountAmt.toFixed(2);
            document.getElementById('removeVoucherBtn').style.display = 'inline-flex';
            document.getElementById('applyVoucherBtn').style.display = 'none';
            updateTotal();
        } else {
            showVoucherMsg(data.msg,'err');
        }
    })
    .catch(()=>{
        document.getElementById('applyVoucherBtn').disabled = false;
        document.getElementById('applyVoucherBtn').textContent = 'Apply';
        showVoucherMsg('Error checking voucher. Try again.','err');
    });
}

function removeVoucher(){
    _discountAmt = 0; _voucherApplied = false;
    document.getElementById('voucherCodeInput').value = '';
    document.getElementById('discountAmountInput').value = '0';
    document.getElementById('voucherDisplay').value = '';
    document.getElementById('discountRow').style.display = 'none';
    document.getElementById('removeVoucherBtn').style.display = 'none';
    document.getElementById('applyVoucherBtn').style.display = 'inline-flex';
    showVoucherMsg('','');
    updateTotal();
}

function showVoucherMsg(msg, type){
    const el = document.getElementById('voucherMsg');
    el.textContent = msg;
    el.className = 'voucher-msg' + (type ? ' '+type : '');
}

function updateTotal(){
    const newTotal = Math.max(0, _baseTotal - _discountAmt);
    document.getElementById('grandTotalDisplay').textContent = 'RM '+newTotal.toFixed(2);
}

// ── Form Validation ───────────────────────────────────────────────
document.getElementById('checkoutForm').addEventListener('submit', function(e){
    let ok = true;

    // Shipping address
    const addr = document.getElementById('shippingAddr').value.trim();
    const addrGrp = document.getElementById('addrGroup');
    if(!addr){
        addrGrp.classList.add('field-invalid');
        document.getElementById('addrErr').style.display='block';
        ok = false;
    } else {
        addrGrp.classList.remove('field-invalid');
        document.getElementById('addrErr').style.display='none';
    }

    // Payment method
    const method = document.getElementById('payMethodVal').value;
    if(!method){
        document.getElementById('payMethodErr').style.display='block';
        ok = false;
    } else {
        document.getElementById('payMethodErr').style.display='none';
    }

    // Bank required
    if(method === 'online_banking'){
        const bankSel = document.querySelector('input[name="bank_name"]:checked');
        const bankErr = document.getElementById('bankErr');
        if(!bankSel){
            bankErr.style.display='inline';
            ok = false;
        } else {
            bankErr.style.display='none';
        }
    }

    // Wallet required
    if(method === 'ewallet'){
        const walletSel = document.querySelector('input[name="wallet_name"]:checked');
        const walletErr = document.getElementById('walletErr');
        if(!walletSel){
            walletErr.style.display='inline';
            ok = false;
        } else {
            walletErr.style.display='none';
        }
    }

    // Credit/debit card fields
    if(method === 'credit_card'){
        const cardNum  = document.getElementById('cardNumber').value.replace(/\s/g,'');
        const expiry   = document.getElementById('cardExpiry').value.trim();
        const cvv      = document.getElementById('cardCvv').value.trim();
        const cardName = document.getElementById('cardName').value.trim();

        if(cardNum.length !== 16){ setFieldErr('grp_card_number','cardNumberErr', true);  ok=false; }
        else                     { setFieldErr('grp_card_number','cardNumberErr', false); }
        if(expiry.length < 7)    { setFieldErr('grp_card_expiry','cardExpiryErr', true);  ok=false; }
        else                     { setFieldErr('grp_card_expiry','cardExpiryErr', false); }
        if(cvv.length !== 3)     { setFieldErr('grp_card_cvv',   'cardCvvErr',    true);  ok=false; }
        else                     { setFieldErr('grp_card_cvv',   'cardCvvErr',    false); }
        if(cardName.length < 2)  { setFieldErr('grp_card_name',  'cardNameErr',   true);  ok=false; }
        else                     { setFieldErr('grp_card_name',  'cardNameErr',   false); }

        // Build last-4 display for receipt
        if(cardNum.length === 16){
            document.getElementById('payDetailVal').value = 'Card ending •••• ' + cardNum.slice(-4);
        }
    }

    if(!ok){
        e.preventDefault();
        // Scroll to first error
        const firstErr = document.querySelector('.field-invalid, [style*="display:block"][id$="Err"]');
        if(firstErr) firstErr.scrollIntoView({behavior:'smooth',block:'center'});
    }
});

function setFieldErr(grpId, errId, hasErr){
    const grp = document.getElementById(grpId);
    const err = document.getElementById(errId);
    if(hasErr){ grp.classList.add('field-invalid'); err.style.display='block'; }
    else { grp.classList.remove('field-invalid'); err.style.display='none'; }
}

// Clear error on input
document.getElementById('shippingAddr').addEventListener('input', function(){
    document.getElementById('addrGroup').classList.remove('field-invalid');
    document.getElementById('addrErr').style.display='none';
});
</script>
</body>
</html>
