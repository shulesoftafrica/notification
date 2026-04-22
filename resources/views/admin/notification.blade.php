<script>
(function() {
    'use strict';
    
    // ========================================
    // --- CONFIGURATION ---
    // Edit these values to customize the widget
    // ========================================
    const WHATSAPP_NUMBER = "255689908004"; // Replace with your WhatsApp number (country code + number)
    const WELCOME_MESSAGE = "Hello SafariChat, I need help with..."; // Default message to send
    
    // ========================================
    // DO NOT EDIT BELOW THIS LINE
    // ========================================
    
    // Create widget HTML structure
    const widgetHTML = `
        <div id="shule-whatsapp-widget">
            <div id="shule-wa-pulse-dot"></div>
            <svg id="shule-wa-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
            </svg>
            <span id="shule-wa-text">Support is here</span>
        </div>
    `;
    
    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initWidget);
    } else {
        initWidget();
    }
    
    function initWidget() {
        // Inject widget HTML
        const container = document.createElement('div');
        container.innerHTML = widgetHTML;
        document.body.appendChild(container.firstElementChild);
        
        // Add click event listener
        const widget = document.getElementById('shule-whatsapp-widget');
        if (widget) {
            widget.addEventListener('click', handleClick);
        }
    }
    
    function handleClick() {
        const encodedMessage = encodeURIComponent(WELCOME_MESSAGE);
        const whatsappURL = `https://wa.me/${WHATSAPP_NUMBER}?text=${encodedMessage}`;
        window.open(whatsappURL, '_blank', 'noopener,noreferrer');
    }
    
})();
</script>

<style>
/* WhatsApp Support Widget Styles - All scoped to avoid conflicts */
#shule-whatsapp-widget {
    position: fixed;
    bottom: 24px;
    right: 24px;
    display: flex;
    align-items: center;
    gap: 10px;
    background: #ffffff;
    padding: 12px 20px;
    border-radius: 50px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    cursor: pointer;
    transition: all 0.3s ease;
    z-index: 9999;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}

#shule-whatsapp-widget:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
    background: #f8f9fa;
}

#shule-wa-icon {
    width: 24px;
    height: 24px;
    color: #25D366;
    flex-shrink: 0;
}

#shule-wa-text {
    font-size: 15px;
    font-weight: 500;
    color: #333333;
    white-space: nowrap;
    user-select: none;
}

#shule-wa-pulse-dot {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 10px;
    height: 10px;
    background: #25D366;
    border-radius: 50%;
    animation: shule-wa-pulse 2s infinite;
}

@keyframes shule-wa-pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.7);
    }
    50% {
        box-shadow: 0 0 0 6px rgba(37, 211, 102, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(37, 211, 102, 0);
    }
}

/* Mobile responsiveness */
@media (max-width: 768px) {
    #shule-whatsapp-widget {
        bottom: 16px;
        right: 16px;
        padding: 10px 16px;
    }
    
    #shule-wa-text {
        font-size: 14px;
    }
    
    #shule-wa-icon {
        width: 20px;
        height: 20px;
    }
}

/* Optional: Hide text on very small screens, show icon only */
@media (max-width: 480px) {
    #shule-wa-text {
        display: none;
    }
    
    #shule-whatsapp-widget {
        padding: 12px;
        border-radius: 50%;
        width: 48px;
        height: 48px;
        justify-content: center;
    }
    
    #shule-wa-pulse-dot {
        top: 4px;
        right: 4px;
    }
}
</style>