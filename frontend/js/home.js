 // Mobile menu functionality
        const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
        const mobileMenu = document.querySelector('.mobile-menu');
        const menuIcon = mobileMenuToggle.querySelector('i');

        mobileMenuToggle.addEventListener('click', function() {
            mobileMenu.classList.toggle('active');
            
            // Toggle icon between hamburger and X
            if (mobileMenu.classList.contains('active')) {
                menuIcon.classList.remove('fa-bars');
                menuIcon.classList.add('fa-times');
            } else {
                menuIcon.classList.remove('fa-times');
                menuIcon.classList.add('fa-bars');
            }
        });

        // Close mobile menu when clicking on a link
        document.querySelectorAll('.mobile-menu a').forEach(link => {
            link.addEventListener('click', function() {
                mobileMenu.classList.remove('active');
                menuIcon.classList.remove('fa-times');
                menuIcon.classList.add('fa-bars');
            });
        });

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('nav')) {
                mobileMenu.classList.remove('active');
                menuIcon.classList.remove('fa-times');
                menuIcon.classList.add('fa-bars');
            }
        });

        // Simple smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add fade-in animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -100px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fade-in-up');
                }
            });
        }, observerOptions);

        // Observe feature cards for animation
        document.querySelectorAll('.feature-card').forEach(card => {
            observer.observe(card);
        });

        // Optimize for mobile performance
        let ticking = false;
        function updateOnScroll() {
            // Add any scroll-based animations here
            ticking = false;
        }

        function requestTick() {
            if (!ticking) {
                requestAnimationFrame(updateOnScroll);
                ticking = true;
            }
        }

        window.addEventListener('scroll', requestTick);

        // Prevent zoom on input focus (iOS Safari)
        document.addEventListener('touchstart', function() {}, true);

        // Handle orientation change
        window.addEventListener('orientationchange', function() {
            setTimeout(function() {
                window.scrollTo(0, 0);
            }, 500);
        });
