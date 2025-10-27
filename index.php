<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Opiña Law Office - Professional Legal Services</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700;800;900&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #5D0E26;
            --primary-dark: #4A0B1E;
            --secondary-color: #8B1538;
            --accent-color: #8B4A6B;
            --text-color: #333;
            --light-gray: #f8f9fa;
            --border-color: #dcdde1;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --info-color: #3498db;
            --gradient-primary: linear-gradient(135deg, #5D0E26, #8B1538);
            --gradient-secondary: linear-gradient(135deg, #8B1538, #5D0E26);
            --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --card-shadow-hover: 0 8px 30px rgba(0, 0, 0, 0.12);
            --border-radius: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            overflow-x: hidden;
        }

        /* Navbar Styles */
        .navbar {
            position: fixed;
            top: 0;
            width: 100%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 1rem 2rem;
            z-index: 1000;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            transition: var(--transition);
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .nav-brand img {
            width: 50px;
            height: 50px;
            border-radius: 8px;
        }

        .nav-brand h1 {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .nav-buttons {
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            cursor: pointer;
            font-size: 0.9rem;
        }

        .btn-outline {
            background: transparent;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        .btn-outline:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--gradient-secondary);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(93, 14, 38, 0.3);
        }

        /* Hero Section */
        .hero {
            background: var(--primary-dark);
            padding: 8rem 2rem 4rem;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="1" fill="%235D0E26" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }

        .hero-content {
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
            z-index: 2;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .hero-text {
            text-align: left;
        }

        .hero-image {
            text-align: center;
        }

        .hero-image img {
            max-width: 100%;
            height: auto;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .hero-image img:hover {
            transform: scale(1.05);
        }

        .hero h2 {
            font-family: 'Playfair Display', serif;
            font-size: 3.5rem;
            font-weight: 700;
            color: white;
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }

        .hero p {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            justify-content: flex-start;
            flex-wrap: wrap;
        }

        .hero .btn-outline {
            background: transparent;
            color: white;
            border: 2px solid white;
        }

        .hero .btn-outline:hover {
            background: white;
            color: var(--primary-color);
            transform: translateY(-2px);
        }

        .hero .btn {
            padding: 1rem 2rem;
            font-size: 1rem;
        }

        /* Services Section */
        .services {
            padding: 5rem 2rem;
            background: white;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .section-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .section-header h3 {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .section-header p {
            font-size: 1.1rem;
            color: var(--accent-color);
            max-width: 600px;
            margin: 0 auto;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }

        .service-card {
            background: white;
            padding: 2.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            text-align: center;
            transition: var(--transition);
            border: 1px solid rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
        }

        .service-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--card-shadow-hover);
        }

        .service-icon {
            width: 80px;
            height: 80px;
            background: var(--gradient-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            font-size: 2rem;
            box-shadow: 0 4px 15px rgba(93, 14, 38, 0.2);
        }

        .service-card h4 {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .service-card p {
            color: var(--accent-color);
            line-height: 1.6;
        }

        /* Features Section */
        .features {
            padding: 5rem 2rem;
            background: var(--light-gray);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }

        .feature-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1.5rem;
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
        }

        .feature-item:hover {
            transform: translateY(-5px);
            box-shadow: var(--card-shadow-hover);
        }

        .feature-icon {
            width: 50px;
            height: 50px;
            background: var(--gradient-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .feature-content h5 {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .feature-content p {
            color: var(--accent-color);
            font-size: 0.9rem;
        }

        /* CTA Section */
        .cta {
            padding: 5rem 2rem;
            background: var(--gradient-primary);
            color: white;
            text-align: center;
        }

        .cta h3 {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .cta p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        .cta-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .cta .btn {
            padding: 1rem 2rem;
            font-size: 1rem;
        }

        .btn-white {
            background: white;
            color: var(--primary-color);
        }

        .btn-white:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
        }

        .btn-transparent {
            background: transparent;
            color: white;
            border: 2px solid white;
        }

        .btn-transparent:hover {
            background: white;
            color: var(--primary-color);
            transform: translateY(-2px);
        }

        /* Footer */
        .footer {
            background: var(--primary-dark);
            color: white;
            padding: 3rem 2rem 1rem;
            text-align: center;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
        }

        .footer-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .footer-brand img {
            width: 60px;
            height: 60px;
            border-radius: 8px;
        }

        .footer-brand h4 {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .footer p {
            opacity: 0.8;
            margin-bottom: 1rem;
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 1rem;
            opacity: 0.6;
            font-size: 0.9rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .navbar {
                padding: 1rem;
            }

            .nav-brand h1 {
                font-size: 1.2rem;
            }

            .hero {
                padding: 6rem 1rem 3rem;
            }

            .hero-content {
                grid-template-columns: 1fr;
                gap: 2rem;
                text-align: center;
            }

            .hero-text {
                text-align: center;
            }

            .hero h2 {
                font-size: 2.5rem;
            }

            .hero p {
                font-size: 1rem;
            }

            .hero-buttons {
                flex-direction: column;
                align-items: center;
                justify-content: center;
            }

            .section-header h3 {
                font-size: 2rem;
            }

            .services-grid {
                grid-template-columns: 1fr;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }

            .cta h3 {
                font-size: 2rem;
            }

            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }
        }

        @media (max-width: 480px) {
            .nav-buttons {
                gap: 0.5rem;
            }

            .btn {
                padding: 0.5rem 1rem;
                font-size: 0.8rem;
            }

            .hero h2 {
                font-size: 2rem;
            }

            .service-card {
                padding: 2rem;
            }

            .feature-item {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand">
                <img src="images/logo.jpg" alt="Opiña Law Office Logo">
                <h1>Opiña Law Office</h1>
            </div>
            <div class="nav-buttons">
                <a href="login_form.php" class="btn btn-outline">Login</a>
                <a href="register_form.php" class="btn btn-primary">Register</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <div class="hero-text">
                <h2>Opiña Law Office</h2>
                <p>Streamline your legal practice with our integrated platform featuring free legal consultations, notary services, secure case management, and professional legal representation. Access comprehensive legal services designed for modern law offices.</p>
                <div class="hero-buttons">
                    <a href="register_form.php" class="btn btn-primary">Get Started Today</a>
                    <a href="#services" class="btn btn-outline">Learn More</a>
                </div>
            </div>
            <div class="hero-image">
                <img src="images/lplogo.png" alt="Professional Legal Services" title="Justice and Legal Excellence">
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section class="services" id="services">
        <div class="container">
            <div class="section-header">
                <h3>Our Comprehensive Services</h3>
                <p>Streamline your legal practice with our integrated platform designed specifically for law offices. <strong>Clients can access our system to track their cases and communicate with legal professionals.</strong></p>
            </div>
            <div class="services-grid">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-gavel"></i>
                    </div>
                    <h4>Free Legal Advice</h4>
                    <p>Get professional legal guidance and consultation at no cost. Our experienced attorneys provide initial legal advice to help you understand your rights and options.</p>
                </div>
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-stamp"></i>
                    </div>
                    <h4>Notary Services</h4>
                    <p>Professional notarization services for all your legal documents. We provide certified notary services to authenticate and validate your important paperwork.</p>
                </div>
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-balance-scale"></i>
                    </div>
                    <h4>Legal Services</h4>
                    <p>Comprehensive legal representation and services including litigation, contract review, legal research, and document preparation for all your legal needs.</p>
                </div>
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h4>Case Scheduling</h4>
                    <p>Schedule court appearances, client meetings, and deadlines with our intuitive calendar system. Never miss an important legal appointment again.</p>
                </div>
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h4>Secure Messaging</h4>
                    <p>Communicate securely with clients and team members through our encrypted messaging platform. Protect sensitive legal communications with bank-level security.</p>
                </div>
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h4>Security & Compliance</h4>
                    <p>Ensure data security and regulatory compliance with our robust security measures and audit trails. Your confidential information is protected with industry-leading security.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features">
        <div class="container">
            <div class="section-header">
                <h3>Why Choose Our Platform?</h3>
                <p>Built by legal professionals for legal professionals</p>
            </div>
            <div class="features-grid">
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <div class="feature-content">
                        <h5>Mobile Responsive</h5>
                        <p>Access your legal practice from anywhere with our mobile-friendly interface.</p>
                    </div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <div class="feature-content">
                        <h5>Fast & Efficient</h5>
                        <p>Streamlined workflows that save time and increase productivity.</p>
                    </div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="feature-content">
                        <h5>Secure & Private</h5>
                        <p>Bank-level security to protect sensitive client information.</p>
                    </div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <div class="feature-content">
                        <h5>24/7 Support</h5>
                        <p>Round-the-clock technical support for uninterrupted service.</p>
                    </div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-sync"></i>
                    </div>
                    <div class="feature-content">
                        <h5>Real-time Updates</h5>
                        <p>Instant notifications and real-time case status updates.</p>
                    </div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-download"></i>
                    </div>
                    <div class="feature-content">
                        <h5>Easy Integration</h5>
                        <p>Seamlessly integrate with your existing legal practice tools.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-brand">
                <img src="images/logo.jpg" alt="Opiña Law Office Logo">
                <h4>Opiña Law Office</h4>
            </div>
            <p>Professional legal services management platform</p>
            <p>Empowering legal professionals with modern technology</p>
            <div class="footer-bottom">
                <p>&copy; 2025 Opiña Law Office. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Smooth scrolling for anchor links
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

        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 100) {
                navbar.style.background = 'rgba(255, 255, 255, 0.98)';
                navbar.style.boxShadow = '0 4px 30px rgba(0, 0, 0, 0.15)';
            } else {
                navbar.style.background = 'rgba(255, 255, 255, 0.95)';
                navbar.style.boxShadow = '0 2px 20px rgba(0, 0, 0, 0.1)';
            }
        });

        // Add animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe service cards and feature items
        document.querySelectorAll('.service-card, .feature-item').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });
    </script>
</body>
</html>
