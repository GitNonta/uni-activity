
    // ปิด dropdown "แนะนำ" เมื่อคลิกข้างนอก
    document.addEventListener('click', function(e) {
        document.querySelectorAll('.recommend-dropdown.open').forEach(function(d) {
            if (!d.contains(e.target)) d.classList.remove('open');
        });
    });
    