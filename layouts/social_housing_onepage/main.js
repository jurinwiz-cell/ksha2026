document.addEventListener('DOMContentLoaded', function() {
    // 1. Swiper Initialization
    const swiper = new Swiper('.notice-swiper', {
        slidesPerView: 1,
        spaceBetween: 20,
        pagination: {
            el: '.swiper-pagination',
            clickable: true,
        },
        breakpoints: {
            640: {
                slidesPerView: 2,
            },
            1024: {
                slidesPerView: 3,
            },
        }
    });

    // 1.1 Navbar Scroll Effect
    const mainNav = document.getElementById('mainNav');
    window.addEventListener('scroll', () => {
        if (window.scrollY > 50) {
            mainNav.classList.add('scrolled');
        } else {
            mainNav.classList.remove('scrolled');
        }
    });

    // 1.2 Mobile Menu Toggle
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mobileMenu = document.getElementById('mobileMenu');
    const mobileLinks = document.querySelectorAll('.mobile-link');

    if (mobileMenuBtn && mobileMenu) {
        mobileMenuBtn.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
            mobileMenu.classList.toggle('flex');
            
            // Prevent body scroll when menu is open
            if(mobileMenu.classList.contains('flex')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        });

        mobileLinks.forEach(link => {
            link.addEventListener('click', () => {
                mobileMenu.classList.add('hidden');
                mobileMenu.classList.remove('flex');
                document.body.style.overflow = '';
            });
        });
    }

    // 2. Chart.js Implementation
    const ctx = document.getElementById('supplyChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['2021', '2022', '2023', '2024', '2025'],
                datasets: [{
                    label: '사회주택 공급 세대수',
                    data: [120, 190, 300, 450, 600],
                    backgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--accent-color').trim() || '#3b82f6',
                    borderRadius: 8,
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            display: false
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }

    // 3. Smooth Scroll for Navigation
    document.querySelectorAll('nav a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelector(this.getAttribute('href')).scrollIntoView({
                behavior: 'smooth'
            });
        });
    });

    // 4. Layout Settings Modal
    const layoutBtns = document.querySelectorAll('.layout-section-btn');
    const layoutModal = document.getElementById('layoutAdminModal');
    const closeLayoutModalBtn = document.getElementById('closeLayoutModalBtn');
    const layoutIframe = document.getElementById('layoutAdminIframe');
    const layoutLoading = document.getElementById('layoutAdminLoading');

    if (layoutBtns.length > 0 && layoutModal && layoutIframe) {
        const adminUrl = layoutIframe.getAttribute('data-admin-url');

        layoutBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const groupIndex = parseInt(this.getAttribute('data-group-index'), 10);
                
                // Show modal
                layoutModal.classList.remove('hidden');
                // Trigger CSS transition
                setTimeout(() => {
                    layoutModal.classList.remove('opacity-0');
                    layoutModal.querySelector('.bg-white').classList.remove('scale-95');
                }, 10);
                
                document.body.style.overflow = 'hidden';
                layoutLoading.style.display = 'flex';
                layoutIframe.style.opacity = '0';
                
                // Set iframe src
                layoutIframe.src = adminUrl;
                
                // Handle iframe load
                layoutIframe.onload = function() {
                    try {
                        const iframeDoc = layoutIframe.contentDocument || layoutIframe.contentWindow.document;
                        
                        // Check if it's the actual layout modify page (it has forms)
                        const form = iframeDoc.querySelector('form');
                        if (form) {
                            // Hide XE admin headers/menus/sidebars
                            const elementsToHide = iframeDoc.querySelectorAll('#header, #gnb, #lnb, .x_header, .x_footer, #xe-gnb, header, footer');
                            elementsToHide.forEach(el => {
                                if(el) el.style.display = 'none';
                            });

                            const isolateTab = () => {
                                // Adjust body padding if needed
                                if(iframeDoc.body) {
                                    iframeDoc.body.style.padding = '0';
                                    iframeDoc.body.style.background = '#fff';
                                }

                                // Hide general layout config elements (title, header script, etc)
                                const directGroups = iframeDoc.querySelectorAll('#layout_config > .x_control-group');
                                directGroups.forEach(el => el.style.display = 'none');

                                // Hide Extra Vars H1 and Menu Section
                                const sections = iframeDoc.querySelectorAll('#layout_config > .section');
                                if (sections.length > 0) {
                                    const h1 = sections[0].querySelector('h1');
                                    if (h1) h1.style.display = 'none'; // Hide "사용자 정의" title
                                }
                                if (sections.length > 1) {
                                    sections[1].style.display = 'none'; // Hide Menu section
                                }

                                // Hide the "List" button
                                const listBtnArea = iframeDoc.querySelector('.btnArea .etc');
                                if (listBtnArea) listBtnArea.style.display = 'none';

                                // Find the group tabs (x-nav-tabs in Rhymix/XE)
                                const navTabsContainer = iframeDoc.querySelector('.x_nav-tabs');
                                if (navTabsContainer) {
                                    navTabsContainer.style.display = 'none'; // Hide the tabs list entirely
                                }

                                const tabs = iframeDoc.querySelectorAll('.x-nav-tabs > li, .nav-tabs > li, .x_nav-tabs > li');
                                const groups = iframeDoc.querySelectorAll('.x-tab-content > section, .tab-content > div, .x_tab-content > div.x_tab-pane');
                                
                                if (groups.length > 0) {
                                    groups.forEach((group, index) => {
                                        if (index === groupIndex) {
                                            group.style.display = 'block';
                                            group.classList.add('x_active', 'active');
                                        } else {
                                            group.style.display = 'none';
                                            group.classList.remove('x_active', 'active');
                                        }
                                    });
                                }

                                // Inject Module Selector Buttons for Target Boards
                                const targetInputs = ['board1_target', 'board2_target', 'board3_target', 'notice_target', 'archive_target'];
                                targetInputs.forEach(name => {
                                    const input = iframeDoc.querySelector('input[name="' + name + '"]');
                                    if (input && (!input.nextElementSibling || !input.nextElementSibling.classList.contains('module-search-btn'))) {
                                        input.style.width = '60%';
                                        input.style.display = 'inline-block';
                                        
                                        const btn = iframeDoc.createElement('button');
                                        btn.type = 'button';
                                        btn.className = 'x_btn module-search-btn';
                                        btn.innerText = '탐색기(게시판 찾기)';
                                        btn.style.marginLeft = '5px';
                                        
                                        btn.onclick = function(e) {
                                            e.preventDefault();
                                            const url = '/index.php?module=module&act=dispModuleSelectList&id=' + name;
                                            if (typeof layoutIframe.contentWindow.popopen === 'function') {
                                                layoutIframe.contentWindow.popopen(url, 'ModuleSelect');
                                            } else {
                                                layoutIframe.contentWindow.open(url, 'ModuleSelect', 'width=800,height=600,scrollbars=yes');
                                            }
                                        };
                                        
                                        input.parentNode.insertBefore(btn, input.nextSibling);
                                    }
                                });

                                // Define the callback function that the Rhymix popup calls
                                layoutIframe.contentWindow.insertSelectedModule = function(id, module_srl, mid, browser_title) {
                                    const input = iframeDoc.querySelector('input[name="' + id + '"]');
                                    if (input) {
                                        input.value = mid;
                                    }
                                };
                            };

                            // Run isolateTab once immediately, then once more after a short delay
                            // to handle any late-running Rhymix scripts. NO polling — no server load.
                            let pendingRetry = null;
                            isolateTab();
                            pendingRetry = setTimeout(() => {
                                isolateTab();
                                pendingRetry = null;
                            }, 600);

                            // Store the pending timeout ID on the iframe element so close button can cancel it
                            layoutIframe._pendingRetry = pendingRetry;

                            // Clean up on form submit
                            let submitListenerAttached = form.getAttribute('data-submit-attached');
                            if (!submitListenerAttached) {
                                form.addEventListener('submit', function() {
                                    if (layoutIframe._pendingRetry) {
                                        clearTimeout(layoutIframe._pendingRetry);
                                        layoutIframe._pendingRetry = null;
                                    }
                                    layoutIframe.onload = function() {
                                        window.location.reload();
                                    };
                                });
                                form.setAttribute('data-submit-attached', 'true');
                            }
                            
                            // Show iframe and hide loading
                            layoutLoading.style.display = 'none';
                            layoutIframe.style.opacity = '1';
                        } else {
                            // After form submission, form might not exist if it redirected somewhere else
                            // If it redirected, reload the parent
                            if(layoutIframe.src !== adminUrl && layoutIframe.src !== '') {
                                window.location.reload();
                            }
                        }

                    } catch(e) {
                        console.error('Cannot access iframe content:', e);
                        // Fallback: just show the iframe even if we can't style it
                        layoutLoading.style.display = 'none';
                        layoutIframe.style.opacity = '1';
                    }
                };
            });
        });

        // Close modal — cancel any pending retry timeout first
        closeLayoutModalBtn.addEventListener('click', function() {
            if (layoutIframe._pendingRetry) {
                clearTimeout(layoutIframe._pendingRetry);
                layoutIframe._pendingRetry = null;
            }
            layoutModal.classList.add('opacity-0');
            layoutModal.querySelector('.bg-white').classList.add('scale-95');
            setTimeout(() => {
                layoutModal.classList.add('hidden');
                document.body.style.overflow = '';
                layoutIframe.src = 'about:blank'; // reset
            }, 300);
        });
    }
});
