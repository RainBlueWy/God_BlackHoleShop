// ธีมสลับ dark/light ใช้สคริปต์ inline ใน index.php เพื่อให้ปุ่มกดได้แน่นอน

// ตรวจเวอร์ชัน/รีเฟรชอัตโนมัติ ใช้สคริปต์ inline ต้น body ในแต่ละหน้า (ทุก 10 วินาที)

// รันหลัง DOM พร้อม (script อยู่ใน head จึงต้องรอให้มี navbar / ปุ่มแฮมเบอร์เกอร์)
function initWhenReady() {
    // แถบข่าวเลื่อน: ปิดแล้วจำใน session (ปุ่ม × แสดงเฉพาะแอดมิน)
    var bar = document.getElementById('tickerBar');
    var closeBtn = document.getElementById('tickerClose');
    if (bar) {
        if (sessionStorage.getItem('tickerClosed') === '1') bar.classList.add('is-hidden');
        if (closeBtn) closeBtn.addEventListener('click', function () { bar.classList.add('is-hidden'); sessionStorage.setItem('tickerClosed', '1'); });
    }

    // Navbar scroll effect
    var navbar = document.getElementById('navbar');
    if (navbar) {
        window.addEventListener('scroll', function () {
            if (window.scrollY > 50) navbar.classList.add('scrolled');
            else navbar.classList.remove('scrolled');
        }, { passive: true });
    }

    // Mobile Menu Toggle (ปุ่ม 3 ขีด – เปิด/ปิดเมนูลิ้นชัก)
    var menuToggle = document.getElementById('menuToggle');
    var navLinks = document.querySelector('.nav-links');

    if (menuToggle && navLinks) {
        function closeMenu() {
            navLinks.classList.remove('open');
            menuToggle.classList.remove('active');
            menuToggle.setAttribute('aria-expanded', 'false');
            var overlay = document.getElementById('navOverlay');
            if (overlay) overlay.remove();
        }

        menuToggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var isOpen = navLinks.classList.toggle('open');
            menuToggle.classList.toggle('active');
            menuToggle.setAttribute('aria-expanded', isOpen);
            if (isOpen) {
                var overlay = document.createElement('div');
                overlay.id = 'navOverlay';
                overlay.className = 'nav-overlay';
                overlay.setAttribute('aria-hidden', 'true');
                overlay.addEventListener('click', closeMenu);
                document.body.appendChild(overlay);
            } else {
                var ov = document.getElementById('navOverlay');
                if (ov) ov.remove();
            }
        });

        navLinks.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', closeMenu);
        });

        var drawerClose = document.getElementById('navDrawerClose');
        if (drawerClose) drawerClose.addEventListener('click', closeMenu);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initWhenReady);
} else {
    initWhenReady();
}

// Scroll Reveal สำหรับ [data-animate]
const animatedElements = document.querySelectorAll('[data-animate]');
if (animatedElements.length) {
    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) entry.target.classList.add('visible');
        });
    }, { rootMargin: '0px 0px -80px 0px', threshold: 0.1 });
    animatedElements.forEach(el => revealObserver.observe(el));
}

// ซ่อน "เลื่อนลง" หลังเลื่อน
const scrollHint = document.getElementById('scrollHint');
if (scrollHint) {
    window.addEventListener('scroll', () => {
        if (window.scrollY > 200) {
            scrollHint.style.opacity = '0';
            scrollHint.style.pointerEvents = 'none';
        } else {
            scrollHint.style.opacity = '';
            scrollHint.style.pointerEvents = '';
        }
    }, { passive: true });
}


// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const href = this.getAttribute('href');
        if (href !== '#' && href !== '#login') {
            e.preventDefault();
            const target = document.querySelector(href);
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        }
    });
});

// Product card click animation
document.querySelectorAll('.product-card').forEach(card => {
    card.addEventListener('click', function (e) {
        // Don't trigger if clicking on the buy button
        if (!e.target.classList.contains('btn')) {
            this.style.transform = 'scale(0.98)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);
        }
    });
});

// Product cards: fade-in เมื่อเข้าหน้าจอ (ใช้ data-animate หรือ class ตามที่มี)
document.querySelectorAll('.product-card:not([data-animate])').forEach((card, index) => {
    card.style.opacity = '0';
    card.style.transform = 'translateY(30px)';
    card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
});
const productCardObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
        }
    });
}, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });
document.querySelectorAll('.product-card').forEach(card => productCardObserver.observe(card));

// Button ripple effect enhancement
document.querySelectorAll('.btn').forEach(button => {
    button.addEventListener('click', function (e) {
        const ripple = document.createElement('span');
        const rect = this.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = e.clientX - rect.left - size / 2;
        const y = e.clientY - rect.top - size / 2;

        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = x + 'px';
        ripple.style.top = y + 'px';
        ripple.classList.add('ripple');

        this.appendChild(ripple);

        setTimeout(() => {
            ripple.remove();
        }, 600);
    });
});

// Add ripple CSS dynamically
const style = document.createElement('style');
style.textContent = `
    .ripple {
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.6);
        transform: scale(0);
        animation: ripple-animation 0.6s ease-out;
        pointer-events: none;
    }
    
    @keyframes ripple-animation {
        to {
            transform: scale(2);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// Parallax effect for hero section
window.addEventListener('scroll', () => {
    const scrolled = window.pageYOffset;
    const heroImage = document.querySelector('.hero-image');
    if (heroImage) {
        heroImage.style.transform = `translateY(${scrolled * 0.3}px)`;
    }
});

// Dynamic gradient animation for gradient text
const gradientTexts = document.querySelectorAll('.gradient-text');
gradientTexts.forEach(text => {
    let hue = 0;
    setInterval(() => {
        hue = (hue + 1) % 360;
        const gradient = `linear-gradient(135deg, 
            hsl(${hue}, 100%, 50%) 0%, 
            hsl(${(hue + 60) % 360}, 100%, 60%) 100%)`;
        text.style.background = gradient;
        text.style.webkitBackgroundClip = 'text';
        text.style.backgroundClip = 'text';
    }, 50);
});

// Product Data
const productData = {
    'seliware': {
        name: 'Seliware 7 วัน',
        category: 'สคริปต์ / สคริปต์ 7 วัน',
        price: '119 บาท',
        image: 'product-seliware.png',
        description: 'Seliware เป็นสคริปต์ Executor ที่มีประสิทธิภาพสูง รองรับเกมส่วนใหญ่บน Roblox พร้อมฟีเจอร์ครบครันและอัปเดตอย่างสม่ำเสมอ เหมาะสำหรับผู้ที่ต้องการประสบการณ์การใช้งานที่ราบรื่น',
        features: [
            'รองรับเกมส่วนใหญ่บน Roblox',
            'อัปเดตอย่างสม่ำเสมอ',
            'ใช้งานง่าย เหมาะสำหรับมือใหม่',
            'ประสิทธิภาพสูง ไม่กระตุก',
            'รองรับ Script Hub มากมาย',
            'ระบบ Anti-Ban ที่ดีเยี่ยม',
            'รองรับ Windows 10/11',
            'ใช้งานได้ 7 วันเต็ม'
        ]
    },
    'wave': {
        name: 'Wave 7 วัน',
        category: 'สคริปต์ / สคริปต์ 7 วัน',
        price: '99 บาท',
        image: 'product-wave.png',
        description: 'Wave Executor เป็นเครื่องมือที่มีประสิทธิภาพสูง พร้อมฟีเจอร์ที่ทันสมัยและใช้งานง่าย เหมาะสำหรับผู้ที่ต้องการ Executor ที่มีเสถียรภาพสูง',
        features: [
            'UI ที่สวยงามและใช้งานง่าย',
            'รองรับ Script หลากหลาย',
            'ประสิทธิภาพสูง ทำงานเร็ว',
            'อัปเดตบ่อย รองรับเกมใหม่',
            'ระบบความปลอดภัยสูง',
            'รองรับ Auto Execute',
            'มี Script Hub ในตัว',
            'ใช้งานได้ 7 วัน'
        ]
    },
    'celery': {
        name: 'Celery 7 วัน',
        category: 'สคริปต์ / สคริปต์ 7 วัน',
        price: '89 บาท',
        image: 'product-celery.png',
        description: 'Celery เป็น Executor ที่มีราคาประหยัดแต่ฟีเจอร์ครบครัน เหมาะสำหรับผู้เริ่มต้นที่ต้องการทดลองใช้งาน Executor คุณภาพดี',
        features: [
            'ราคาประหยัด คุ้มค่า',
            'ฟีเจอร์ครบครัน',
            'ใช้งานง่าย เหมาะมือใหม่',
            'รองรับเกมยอดนิยม',
            'มี Script Library',
            'อัปเดตสม่ำเสมอ',
            'ระบบ Anti-Kick',
            'ใช้งานได้ 7 วัน'
        ]
    },
    'fluxus': {
        name: 'Fluxus 30 วัน',
        category: 'สคริปต์ / สคริปต์ 30 วัน',
        price: '299 บาท',
        image: 'product-fluxus.png',
        description: 'Fluxus เป็น Executor ระดับพรีเมี่ยมที่ใช้งานได้ยาวนาน 30 วัน พร้อมฟีเจอร์ขั้นสูงและการรองรับที่ดีเยี่ยม คุ้มค่าสำหรับผู้ใช้งานระยะยาว',
        features: [
            'ใช้งานได้ยาวนาน 30 วัน',
            'ฟีเจอร์ระดับพรีเมี่ยม',
            'รองรับเกมทุกประเภท',
            'UI ที่ทันสมัยและสวยงาม',
            'มี Built-in Scripts มากมาย',
            'ระบบ Auto Update',
            'Support ตลอด 24 ชั่วโมง',
            'คุ้มค่าที่สุด'
        ]
    },
    'synapse': {
        name: 'Synapse X 7 วัน',
        category: 'สคริปต์ / สคริปต์ 7 วัน',
        price: '149 บาท',
        image: 'product-synapse.png',
        description: 'Synapse X เป็น Executor ที่มีชื่อเสียงและได้รับความนิยมสูงสุด มีฟีเจอร์ที่ทรงพลังและเสถียรภาพสูง เหมาะสำหรับผู้ที่ต้องการคุณภาพระดับท็อป',
        features: [
            'Executor ที่ดีที่สุดในตลาด',
            'รองรับ Script ทุกประเภท',
            'ประสิทธิภาพสูงสุด',
            'UI ที่ใช้งานง่าย',
            'มี Script Hub ครบครัน',
            'อัปเดตทันทีเมื่อเกมอัปเดต',
            'ระบบความปลอดภัยระดับสูง',
            'ใช้งานได้ 7 วัน'
        ]
    },
    'krnl': {
        name: 'KRNL 7 วัน',
        category: 'สคริปต์ / สคริปต์ 7 วัน',
        price: 'ฟรี',
        image: 'product-krnl.png',
        description: 'KRNL เป็น Executor ฟรีที่มีคุณภาพดี เหมาะสำหรับผู้ที่ต้องการทดลองใช้งาน Executor โดยไม่ต้องเสียค่าใช้จ่าย',
        features: [
            'ใช้งานฟรี ไม่มีค่าใช้จ่าย',
            'ฟีเจอร์พื้นฐานครบครัน',
            'รองรับเกมยอดนิยม',
            'ใช้งานง่าย',
            'มี Script Library',
            'อัปเดตสม่ำเสมอ',
            'เหมาะสำหรับมือใหม่',
            'ใช้งานได้ 7 วัน'
        ]
    },
    'limited': {
        name: 'ReaperX | Limited Edition',
        category: 'หมวดหมู่พิเศษ',
        price: '119 บาท',
        image: 'product-limited.png',
        description: 'ReaperX Limited Edition เป็นแพ็คเกจพิเศษที่รวมฟีเจอร์เด่นและสิทธิพิเศษมากมาย สำหรับสมาชิกที่ต้องการประสบการณ์พิเศษ',
        features: [
            'แพ็คเกจพิเศษจำกัดจำนวน',
            'ฟีเจอร์พิเศษเฉพาะสมาชิก',
            'รองรับทุกเกม',
            'Priority Support',
            'อัปเดตก่อนใคร',
            'Script Premium ฟรี',
            'สิทธิพิเศษมากมาย',
            'คุ้มค่าสุดๆ'
        ]
    },
    'executor': {
        name: 'ReaperX | Executor',
        category: 'หมวดหมู่พิเศษ',
        price: '119 บาท',
        image: 'product-executor.png',
        description: 'ReaperX Executor เป็น Executor ที่พัฒนาโดยทีมงาน ReaperX มีฟีเจอร์ที่ทรงพลังและใช้งานง่าย พร้อมการอัปเดตอย่างต่อเนื่อง',
        features: [
            'พัฒนาโดยทีม ReaperX',
            'ฟีเจอร์ครบครัน',
            'UI สวยงาม ใช้งานง่าย',
            'รองรับเกมทุกประเภท',
            'มี Script Hub ในตัว',
            'อัปเดตบ่อย',
            'ระบบความปลอดภัยสูง',
            'ใช้งานได้ 7 วัน'
        ]
    },
    'reset': {
        name: 'ReaperX | Reset HWID',
        category: 'บริการพิเศษ',
        price: '119 บาท',
        image: 'product-reset.png',
        description: 'บริการ Reset HWID สำหรับผู้ที่ต้องการเปลี่ยนเครื่องหรือติดตั้งใหม่ สามารถใช้ Key เดิมได้อีกครั้ง',
        features: [
            'Reset HWID ได้ทันที',
            'ใช้ Key เดิมได้อีกครั้ง',
            'เปลี่ยนเครื่องได้',
            'ติดตั้งใหม่ได้',
            'รวดเร็ว ทันใจ',
            'ไม่ต้องซื้อ Key ใหม่',
            'Support ตลอด 24 ชม.',
            'คุ้มค่า ประหยัด'
        ]
    },
    'website': {
        name: 'ReaperX | Rent Website',
        category: 'บริการพิเศษ',
        price: '119 บาท',
        image: 'product-website.png',
        description: 'บริการเช่าเว็บไซต์สำหรับขายสคริปต์ พร้อมระบบจัดการที่ครบครัน เหมาะสำหรับผู้ที่ต้องการเปิดร้านขายสคริปต์',
        features: [
            'เว็บไซต์สำเร็จรูป',
            'ระบบจัดการครบครัน',
            'ดีไซน์สวยงาม',
            'รองรับการชำระเงิน',
            'ระบบสมาชิก',
            'แก้ไขได้ตามต้องการ',
            'Support ตลอด 24 ชม.',
            'เหมาะสำหรับเปิดร้าน'
        ]
    }
};

// Load product details on product page
if (window.location.pathname.includes('product.php')) {
    const urlParams = new URLSearchParams(window.location.search);
    const productId = urlParams.get('id');

    if (productId && productData[productId]) {
        const product = productData[productId];

        // Update page title
        document.title = `${product.name} - ReaperX Hub`;

        // Update category badge
        const categoryBadge = document.querySelector('.product-detail-info > div');
        if (categoryBadge) {
            categoryBadge.innerHTML = product.category;
        }

        // Update product name
        const productTitle = document.querySelector('.product-detail-info h1');
        if (productTitle) {
            productTitle.textContent = product.name;
        }

        // Update price
        const priceElement = document.querySelector('.price-box .price');
        if (priceElement) {
            priceElement.textContent = `ราคา: ${product.price}`;
        }

        // Update description
        const descriptionElement = document.querySelector('.glass-card p');
        if (descriptionElement) {
            descriptionElement.textContent = product.description;
        }

        // Update features list
        const featuresList = document.querySelector('.features-list');
        if (featuresList && product.features) {
            featuresList.innerHTML = product.features.map(feature => `<li>${feature}</li>`).join('');
        }

        // Update product image
        const productImage = document.querySelector('.main-image');
        if (productImage) {
            productImage.src = product.image;
            productImage.alt = product.name;
        }

        // Update category breadcrumb in heading
        const categoryHeading = document.querySelector('.product-detail-info h1');
        if (categoryHeading) {
            const categorySpan = document.createElement('div');
            categorySpan.style.fontSize = '0.875rem';
            categorySpan.style.color = 'var(--text-secondary)';
            categorySpan.style.marginBottom = 'var(--spacing-sm)';
            categorySpan.innerHTML = `หมวดหมู่: <span class="gradient-text">${product.category}</span>`;
            categoryHeading.parentNode.insertBefore(categorySpan, categoryHeading);
        }

        // Update purchase button link
        const purchaseBtn = document.getElementById('purchaseBtn');
        if (purchaseBtn) {
            purchaseBtn.href = `checkout.php?id=${productId}`;
        }
    }
}

// ===== FRONTEND PROTECTION (ANTI-INSPECT) =====

// 1. คลิกขวา: กันเมื่อ window.DISABLE_RIGHT_CLICK === true (ตั้งใน config.php)
if (window.DISABLE_RIGHT_CLICK) {
    document.addEventListener('contextmenu', e => e.preventDefault());
    window.oncontextmenu = function () { return false; };
}

// 2. Disable Shortcut Keys
document.addEventListener('keydown', (e) => {
    // Disable F12
    if (e.key === 'F12' || e.keyCode === 123) {
        e.preventDefault();
        return false;
    }

    // Disable Ctrl+Shift+I (Inspect), J (Console), C (Selector)
    if (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'J' || e.key === 'C' || e.keyCode === 73 || e.keyCode === 74 || e.keyCode === 67)) {
        e.preventDefault();
        return false;
    }

    // Disable Ctrl+U (View Source)
    if (e.ctrlKey && (e.key === 'u' || e.keyCode === 85)) {
        e.preventDefault();
        return false;
    }

    // Disable Ctrl+S (Save Page)
    if (e.ctrlKey && (e.key === 's' || e.keyCode === 83)) {
        e.preventDefault();
        return false;
    }
}, false);

// 3. Debugger Loop (Heavy Mode - Stalls DevTools)
(function () {
    (function a() {
        try {
            (function b(i) {
                if (("" + i / i).length !== 1 || i % 20 === 0) {
                    (function () { }).constructor("debugger")();
                } else {
                    debugger;
                }
                b(++i);
            })(0);
        } catch (e) {
            setTimeout(a, 50);
        }
    })();
})();

console.log("%c⚠️ Warning: This area is protected! ⚠️", "color: red; font-size: 20px; font-weight: bold;");
console.log("%cIf you are here, you are trying to do something you shouldn't.", "color: orange; font-size: 14px;");
