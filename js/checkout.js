// Checkout functionality
let currentStep = 1;
let checkoutData = {
    shipping: {},
    payment: {},
    cart: []
};

function openCheckout(cartItems, total) {
    checkoutData.cart = cartItems;
    checkoutData.total = total;
    
    document.getElementById('checkoutModal').classList.add('active');
    document.body.style.overflow = 'hidden';
    
    // Pre-fill user data if logged in
    if (currentUser) {
        document.getElementById('firstName').value = currentUser.full_name ? currentUser.full_name.split(' ')[0] : '';
        document.getElementById('lastName').value = currentUser.full_name ? currentUser.full_name.split(' ').slice(1).join(' ') : '';
        document.getElementById('email').value = currentUser.email || '';
        document.getElementById('phone').value = currentUser.phone || '';
        document.getElementById('address').value = currentUser.address || '';
    }
}

function closeCheckout() {
    document.getElementById('checkoutModal').classList.remove('active');
    document.body.style.overflow = 'auto';
    resetCheckout();
}

function resetCheckout() {
    currentStep = 1;
    showStep(1);
    document.querySelectorAll('.step').forEach(s => {
        s.classList.remove('active', 'completed');
    });
    document.querySelector('.step[data-step="1"]').classList.add('active');
}

function nextStep(step) {
    // Validate current step
    if (currentStep === 1 && !validateShipping()) {
        return;
    }
    if (currentStep === 2 && !validatePayment()) {
        return;
    }
    
    // Save current step data
    if (currentStep === 1) {
        checkoutData.shipping = {
            firstName: document.getElementById('firstName').value,
            lastName: document.getElementById('lastName').value,
            email: document.getElementById('email').value,
            phone: document.getElementById('phone').value,
            address: document.getElementById('address').value,
            city: document.getElementById('city').value,
            postal: document.getElementById('postal').value
        };
    }
    
    if (currentStep === 2) {
        const paymentMethod = document.querySelector('input[name="payment"]:checked').value;
        checkoutData.payment = {
            method: paymentMethod
        };
        
        if (paymentMethod === 'card') {
            checkoutData.payment.cardNumber = document.getElementById('cardNumber').value;
            checkoutData.payment.expiry = document.getElementById('expiry').value;
            checkoutData.payment.cvv = document.getElementById('cvv').value;
            checkoutData.payment.cardName = document.getElementById('cardName').value;
        }
    }
    
    // Mark current step as completed
    document.querySelector(`.step[data-step="${currentStep}"]`).classList.add('completed');
    document.querySelector(`.step[data-step="${currentStep}"]`).classList.remove('active');
    
    // Show next step
    currentStep = step;
    showStep(step);
    document.querySelector(`.step[data-step="${step}"]`).classList.add('active');
    
    // Show summary on step 3
    if (step === 3) {
        showOrderSummary();
    }
}

function prevStep(step) {
    document.querySelector(`.step[data-step="${currentStep}"]`).classList.remove('active');
    currentStep = step;
    showStep(step);
    document.querySelector(`.step[data-step="${step}"]`).classList.add('active');
}

function showStep(step) {
    document.querySelectorAll('.checkout-step-content').forEach(content => {
        content.style.display = 'none';
    });
    document.getElementById(`step${step}`).style.display = 'block';
}

function validateShipping() {
    const fields = ['firstName', 'lastName', 'email', 'phone', 'address', 'city', 'postal'];
    let isValid = true;
    
    fields.forEach(field => {
        const input = document.getElementById(field);
        if (!input.value.trim()) {
            input.style.borderColor = '#ff4757';
            isValid = false;
        } else {
            input.style.borderColor = '#e0e0e0';
        }
    });
    
    if (!isValid) {
        alert('Please fill in all required fields');
    }
    
    return isValid;
}

function validatePayment() {
    const paymentMethod = document.querySelector('input[name="payment"]:checked').value;
    
    if (paymentMethod === 'card') {
        const fields = ['cardNumber', 'expiry', 'cvv', 'cardName'];
        let isValid = true;
        
        fields.forEach(field => {
            const input = document.getElementById(field);
            if (!input.value.trim()) {
                input.style.borderColor = '#ff4757';
                isValid = false;
            } else {
                input.style.borderColor = '#e0e0e0';
            }
        });
        
        if (!isValid) {
            alert('Please fill in all payment details');
            return false;
        }
        
        // Validate card number (basic)
        const cardNumber = document.getElementById('cardNumber').value.replace(/\s/g, '');
        if (cardNumber.length < 13 || cardNumber.length > 19) {
            alert('Invalid card number');
            return false;
        }
    }
    
    return true;
}

function showOrderSummary() {
    const summaryDiv = document.getElementById('orderSummary');
    let summaryHTML = '<h4 style="margin-bottom: 1rem;">Items</h4>';
    
    checkoutData.cart.forEach(item => {
        summaryHTML += `
            <div class="order-item">
                <div>
                    <div style="font-weight: 600;">${item.name}</div>
                    <div style="color: #666; font-size: 0.9rem;">Qty: ${item.quantity}</div>
                </div>
                <div style="font-weight: 600;">$${(item.price * item.quantity).toFixed(2)}</div>
            </div>
        `;
    });
    
    summaryHTML += `
        <h4 style="margin: 1.5rem 0 1rem;">Shipping To</h4>
        <div style="color: #666; line-height: 1.8;">
            ${checkoutData.shipping.firstName} ${checkoutData.shipping.lastName}<br>
            ${checkoutData.shipping.address}<br>
            ${checkoutData.shipping.city}, ${checkoutData.shipping.postal}<br>
            ${checkoutData.shipping.email}<br>
            ${checkoutData.shipping.phone}
        </div>
        
        <h4 style="margin: 1.5rem 0 1rem;">Payment Method</h4>
        <div style="color: #666;">
            ${checkoutData.payment.method === 'card' ? 'Credit Card' : 
              checkoutData.payment.method === 'paypal' ? 'PayPal' : 'GCash'}
            ${checkoutData.payment.cardNumber ? 
              `<br>Card ending in ${checkoutData.payment.cardNumber.slice(-4)}` : ''}
        </div>
    `;
    
    summaryDiv.innerHTML = summaryHTML;
    document.getElementById('finalTotal').textContent = '$' + checkoutData.total.toFixed(2);
}

async function placeOrder() {
    const btn = document.querySelector('.btn-place-order');
    const originalText = btn.textContent;
    
    btn.disabled = true;
    btn.innerHTML = '<span class="loading-spinner"></span>Processing...';
    
    // Simulate payment processing
    setTimeout(async () => {
        try {
            // If user is logged in, save order to database
            if (currentUser) {
                const response = await fetch('./api/create-order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        items: checkoutData.cart,
                        total: checkoutData.total,
                        shipping: checkoutData.shipping,
                        payment_method: checkoutData.payment.method
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showSuccessModal(data.order_number);
                } else {
                    alert('Order failed: ' + data.message);
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            } else {
                // Guest checkout
                const orderNumber = 'ORD-' + Date.now();
                showSuccessModal(orderNumber);
            }
            
            // Clear cart
            cart = [];
            saveCart();
            updateCartUI();
            
        } catch (error) {
            console.error('Order error:', error);
            alert('An error occurred. Please try again.');
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }, 2000);
}

function showSuccessModal(orderNumber) {
    closeCheckout();
    
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
    `;
    
    modal.innerHTML = `
        <div style="background: white; padding: 3rem; border-radius: 16px; text-align: center; max-width: 500px; animation: slideUp 0.3s ease;">
            <div style="width: 80px; height: 80px; background: #26de81; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 3rem;">✓</div>
            <h2 style="margin-bottom: 1rem; font-size: 2rem;">Order Placed Successfully!</h2>
            <p style="color: #666; margin-bottom: 1rem; line-height: 1.6;">Your order number is:<br><strong style="font-size: 1.2rem; color: #000;">${orderNumber}</strong></p>
            <p style="color: #666; margin-bottom: 2rem;">Thank you for shopping with LUXE. We'll send you a confirmation email shortly.</p>
            <button onclick="this.closest('div').parentElement.remove()" style="padding: 1rem 3rem; background: #000; color: #fff; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 1rem;">Continue Shopping</button>
        </div>
    `;
    
    document.body.appendChild(modal);
}

// Payment method selection
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.payment-method').forEach(method => {
        method.addEventListener('click', function() {
            document.querySelectorAll('.payment-method').forEach(m => m.classList.remove('active'));
            this.classList.add('active');
            this.querySelector('input').checked = true;
        });
    });
    
    // Card number formatting
    const cardNumberInput = document.getElementById('cardNumber');
    if (cardNumberInput) {
        cardNumberInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '');
            let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
            e.target.value = formattedValue;
        });
    }
    
    // Expiry formatting
    const expiryInput = document.getElementById('expiry');
    if (expiryInput) {
        expiryInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\//g, '');
            if (value.length >= 2) {
                value = value.slice(0, 2) + '/' + value.slice(2, 4);
            }
            e.target.value = value;
        });
    }
});