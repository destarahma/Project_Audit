// Main JavaScript file for Self Audit System

document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
    
    // Confirm delete actions
    const deleteLinks = document.querySelectorAll('a[href*="delete"]');
    deleteLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirm('Apakah Anda yakin ingin menghapus data ini?')) {
                e.preventDefault();
            }
        });
    });
    
    // Auto-calculate total price in Mix Oil form
    const quantityInput = document.querySelector('input[name="quantity"]');
    const unitPriceInput = document.querySelector('input[name="unit_price"]');
    const totalPriceInput = document.querySelector('input[name="total_price"]');
    
    if (quantityInput && unitPriceInput && totalPriceInput) {
        function calculateTotal() {
            const qty = parseFloat(quantityInput.value) || 0;
            const price = parseFloat(unitPriceInput.value.replace(/[^0-9]/g, '')) || 0;
            const total = qty * price;
            
            if (total > 0) {
                totalPriceInput.value = 'Rp ' + total.toLocaleString('id-ID');
            }
        }
        
        quantityInput.addEventListener('input', calculateTotal);
        unitPriceInput.addEventListener('input', calculateTotal);
    }
    
    // Format currency inputs
    const currencyInputs = document.querySelectorAll('input[placeholder*="Rp"]');
    currencyInputs.forEach(input => {
        input.addEventListener('blur', function() {
            let value = this.value.replace(/[^0-9]/g, '');
            if (value) {
                this.value = 'Rp ' + parseInt(value).toLocaleString('id-ID');
            }
        });
        
        input.addEventListener('focus', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    });
});

// Close modal when clicking outside
window.onclick = function(event) {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    });
}

// Print function
function printAudit() {
    window.print();
}

// Export to PDF (basic implementation)
function exportToPDF() {
    alert('Fitur export PDF akan segera tersedia');
}
