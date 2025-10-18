<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FiraTech | Multimedia Portfolio</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Base Styles */
        :root {
            --primary: #0a0a0a;
            --secondary: #ff2a6d;
            --accent: #05d9e8;
            --light: #ffffff;
            --dark: #01012b;
            --gray: #888;
            --neon-glow: 0 0 10px var(--accent), 0 0 20px var(--accent), 0 0 40px var(--accent);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        html {
            scroll-behavior: smooth;
        }
        
        body {
            background-color: var(--primary);
            color: var(--light);
            line-height: 1.6;
            overflow-x: hidden;
        }
        
        .container {
            width: 90%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        section {
            padding: 100px 0;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 60px;
            position: relative;
        }
        
        .section-title h2 {
            font-size: 3rem;
            margin-bottom: 15px;
            background: linear-gradient(45deg, var(--secondary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .section-title p {
            color: var(--gray);
            font-size: 1.2rem;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .glow-btn {
            display: inline-block;
            padding: 15px 35px;
            background: transparent;
            color: var(--accent);
            border: 2px solid var(--accent);
            border-radius: 30px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .glow-btn:before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(5, 217, 232, 0.4), transparent);
            transition: 0.5s;
            z-index: -1;
        }
        
        .glow-btn:hover {
            color: var(--dark);
            box-shadow: var(--neon-glow);
        }
        
        .glow-btn:hover:before {
            left: 100%;
        }
        
        .glow-btn:after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: var(--accent);
            transform: scaleX(0);
            transform-origin: right;
            transition: transform 0.5s;
            z-index: -1;
        }
        
        .glow-btn:hover:after {
            transform: scaleX(1);
            transform-origin: left;
        }
        
        /* Header Styles */
        header {
            padding: 20px 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            background-color: rgba(10, 10, 10, 0.9);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        
        header.scrolled {
            padding: 15px 0;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 28px;
            font-weight: 800;
            color: var(--light);
            display: flex;
            align-items: center;
        }
        
        .logo span {
            color: var(--secondary);
            margin-left: 5px;
        }
        
        .logo i {
            color: var(--accent);
            margin-right: 10px;
            font-size: 24px;
        }
        
        nav ul {
            display: flex;
            list-style: none;
        }
        
        nav ul li {
            margin-left: 30px;
        }
        
        nav ul li a {
            font-weight: 600;
            position: relative;
            padding: 5px 0;
        }
        
        nav ul li a:after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background-color: var(--secondary);
            transition: width 0.3s ease;
        }
        
        nav ul li a:hover:after {
            width: 100%;
        }
        
        .mobile-menu {
            display: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--light);
        }
        
        /* Hero Section */
        .hero {
            height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }
        
        .hero-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(rgba(1, 1, 43, 0.8), rgba(10, 10, 10, 0.9)), url('https://images.unsplash.com/photo-1531297484001-80022131f5a1?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2020&q=80') no-repeat center center/cover;
            z-index: -1;
        }
        
        .hero-content {
            max-width: 800px;
            z-index: 2;
        }
        
        .hero-content h1 {
            font-size: 4.5rem;
            margin-bottom: 20px;
            line-height: 1.2;
            text-transform: uppercase;
        }
        
        .hero-content h1 span {
            color: var(--secondary);
            display: block;
        }
        
        .hero-content p {
            font-size: 1.3rem;
            margin-bottom: 30px;
            color: var(--gray);
        }
        
        .hero-btns {
            display: flex;
            gap: 20px;
        }
        
        .hero-btns .glow-btn:nth-child(2) {
            background: transparent;
            color: var(--light);
            border-color: var(--light);
        }
        
        .hero-btns .glow-btn:nth-child(2):hover {
            color: var(--dark);
            background: var(--light);
            box-shadow: 0 0 10px var(--light), 0 0 20px var(--light);
        }
        
        /* Portfolio Section */
        .portfolio {
            background-color: var(--dark);
            position: relative;
        }
        
        .portfolio::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('https://images.unsplash.com/photo-1550684376-efcbd6e3f031?ixlib=rb-4.0.3&auto=format&fit=crop&w=1950&q=80') no-repeat center center/cover;
            opacity: 0.05;
            z-index: 0;
        }
        
        .portfolio .container {
            position: relative;
            z-index: 1;
        }
        
        .filter-buttons {
            display: flex;
            justify-content: center;
            margin-bottom: 40px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .filter-btn {
            padding: 10px 25px;
            background: transparent;
            border: 1px solid var(--accent);
            color: var(--accent);
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .filter-btn.active, .filter-btn:hover {
            background-color: var(--accent);
            color: var(--dark);
            box-shadow: var(--neon-glow);
        }
        
        .portfolio-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
        }
        
        .portfolio-item {
            border-radius: 10px;
            overflow: hidden;
            position: relative;
            height: 300px;
            cursor: pointer;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            transition: all 0.5s ease;
        }
        
        .portfolio-item:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
        }
        
        .portfolio-item img, .portfolio-item video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .portfolio-item:hover img, .portfolio-item:hover video {
            transform: scale(1.1);
        }
        
        .portfolio-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to bottom, transparent, rgba(1, 1, 43, 0.9));
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            align-items: flex-start;
            padding: 30px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .portfolio-item:hover .portfolio-overlay {
            opacity: 1;
        }
        
        .portfolio-overlay h3 {
            margin-bottom: 10px;
            font-size: 1.5rem;
            color: var(--light);
        }
        
        .portfolio-overlay p {
            color: var(--gray);
            margin-bottom: 15px;
        }
        
        .portfolio-overlay .category {
            display: inline-block;
            padding: 5px 15px;
            background-color: var(--secondary);
            color: var(--light);
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        /* About Section */
        .about {
            background-color: var(--primary);
            position: relative;
        }
        
        .about-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
        }
        
        .about-text h2 {
            font-size: 2.5rem;
            margin-bottom: 20px;
            background: linear-gradient(45deg, var(--secondary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .about-text p {
            margin-bottom: 20px;
            color: var(--gray);
            font-size: 1.1rem;
        }
        
        .skills {
            margin-top: 30px;
        }
        
        .skill-item {
            margin-bottom: 20px;
        }
        
        .skill-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .skill-bar {
            height: 8px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .skill-progress {
            height: 100%;
            background: linear-gradient(90deg, var(--secondary), var(--accent));
            border-radius: 10px;
            position: relative;
        }
        
        .skill-progress:after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            animation: shimmer 2s infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        .about-image {
            border-radius: 10px;
            overflow: hidden;
            height: 500px;
            position: relative;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }
        
        .about-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .about-image:before {
            content: '';
            position: absolute;
            top: -10px;
            left: -10px;
            right: -10px;
            bottom: -10px;
            border: 2px solid var(--accent);
            border-radius: 15px;
            z-index: -1;
            opacity: 0.5;
        }
        
        /* Contact Section */
        .contact {
            background-color: var(--dark);
            position: relative;
        }
        
        .contact-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
        }
        
        .contact-info h3 {
            font-size: 1.8rem;
            margin-bottom: 20px;
            color: var(--light);
        }
        
        .contact-info p {
            margin-bottom: 30px;
            color: var(--gray);
        }
        
        .contact-details {
            margin-bottom: 40px;
        }
        
        .contact-detail {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .contact-detail i {
            width: 50px;
            height: 50px;
            background: linear-gradient(45deg, var(--secondary), var(--accent));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 20px;
        }
        
        .social-links {
            display: flex;
            gap: 15px;
        }
        
        .social-link {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            font-size: 20px;
            position: relative;
            overflow: hidden;
        }
        
        .social-link:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, var(--secondary), var(--accent));
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1;
        }
        
        .social-link i {
            position: relative;
            z-index: 2;
        }
        
        .social-link:hover:before {
            opacity: 1;
        }
        
        .social-link:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
        }
        
        .contact-form {
            background-color: rgba(255, 255, 255, 0.05);
            padding: 40px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 15px;
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 5px;
            color: white;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 10px rgba(5, 217, 232, 0.3);
        }
        
        .form-group textarea {
            height: 150px;
            resize: vertical;
        }
        
        /* Footer */
        footer {
            background-color: var(--primary);
            padding: 50px 0 20px;
            text-align: center;
            color: var(--gray);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .footer-logo {
            font-size: 24px;
            font-weight: 700;
            color: var(--light);
        }
        
        .footer-logo span {
            color: var(--secondary);
        }
        
        .footer-links {
            display: flex;
            gap: 20px;
        }
        
        .footer-links a {
            color: var(--gray);
            transition: color 0.3s ease;
        }
        
        .footer-links a:hover {
            color: var(--accent);
        }
        
        .copyright {
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.95);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .modal-content {
            max-width: 90%;
            max-height: 90%;
            position: relative;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0 50px rgba(5, 217, 232, 0.3);
        }
        
        .modal-content img, .modal-content video {
            width: 100%;
            height: auto;
            max-height: 80vh;
            display: block;
        }
        
        .close-modal {
            position: absolute;
            top: 20px;
            right: 20px;
            color: white;
            font-size: 30px;
            cursor: pointer;
            background: rgba(0, 0, 0, 0.5);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .close-modal:hover {
            background: rgba(255, 42, 109, 0.8);
            transform: rotate(90deg);
        }
        
        /* Responsive Styles */
        @media (max-width: 1100px) {
            .hero-content h1 {
                font-size: 3.5rem;
            }
            
            .about-content, .contact-content {
                grid-template-columns: 1fr;
                gap: 40px;
            }
            
            .about-image {
                order: -1;
                height: 400px;
            }
        }
        
        @media (max-width: 768px) {
            .header-content nav {
                display: none;
            }
            
            .mobile-menu {
                display: block;
            }
            
            .hero-content h1 {
                font-size: 2.8rem;
            }
            
            .hero-btns {
                flex-direction: column;
                gap: 15px;
            }
            
            .portfolio-grid {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            }
            
            .footer-content {
                flex-direction: column;
                gap: 20px;
            }
            
            .section-title h2 {
                font-size: 2.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .hero-content h1 {
                font-size: 2.2rem;
            }
            
            .portfolio-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-buttons {
                gap: 10px;
            }
            
            .filter-btn {
                padding: 8px 15px;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header id="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-bolt"></i>Fira<span>Tech</span>
                </div>
                <nav>
                    <ul>
                        <li><a href="#home">Home</a></li>
                        <li><a href="#portfolio">Portfolio</a></li>
                        <li><a href="#about">About</a></li>
                        <li><a href="#contact">Contact</a></li>
                    </ul>
                </nav>
                <div class="mobile-menu">
                    <i class="fas fa-bars"></i>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="hero-bg"></div>
        <div class="container">
            <div class="hero-content">
                <h1>Multimedia <span>Innovator</span></h1>
                <p>Creating immersive digital experiences through video, audio, and visual design. Pushing creative boundaries across all platforms.</p>
                <div class="hero-btns">
                    <a href="#portfolio" class="glow-btn">View My Work</a>
                    <a href="#contact" class="glow-btn">Get In Touch</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Portfolio Section -->
    <section class="portfolio" id="portfolio">
        <div class="container">
            <div class="section-title">
                <h2>My Portfolio</h2>
                <p>A showcase of my latest creative projects across multiple media formats</p>
            </div>
            
            <div class="filter-buttons">
                <button class="filter-btn active" data-filter="all">All Work</button>
                <button class="filter-btn" data-filter="video">Video Production</button>
                <button class="filter-btn" data-filter="audio">Audio Engineering</button>
                <button class="filter-btn" data-filter="image">Visual Design</button>
            </div>
            
            <div class="portfolio-grid">
                <!-- Video Items -->
                <div class="portfolio-item" data-category="video">
                    <video muted loop>
                        <source src="https://assets.mixkit.co/videos/preview/mixkit-man-dancing-under-changing-lights-1240-large.mp4" type="video/mp4">
                    </video>
                    <div class="portfolio-overlay">
                        <span class="category">Video</span>
                        <h3>Motion Graphics</h3>
                        <p>Animated visual effects project with dynamic lighting</p>
                    </div>
                </div>
                
                <div class="portfolio-item" data-category="video">
                    <video muted loop>
                        <source src="https://assets.mixkit.co/videos/preview/mixkit-tree-with-yellow-flowers-1173-large.mp4" type="video/mp4">
                    </video>
                    <div class="portfolio-overlay">
                        <span class="category">Video</span>
                        <h3>Nature Documentary</h3>
                        <p>Short film exploring natural environments</p>
                    </div>
                </div>
                
                <!-- Audio Items -->
                <div class="portfolio-item" data-category="audio">
                    <img src="https://images.unsplash.com/photo-1598488035139-bdbb2231ce04?ixlib=rb-4.0.3&auto=format&fit=crop&w=1350&q=80" alt="Audio Production">
                    <div class="portfolio-overlay">
                        <span class="category">Audio</span>
                        <h3>Electronic Track</h3>
                        <p>Original composition and professional mixing</p>
                    </div>
                </div>
                
                <div class="portfolio-item" data-category="audio">
                    <img src="https://images.unsplash.com/photo-1571974599782-87624638275f?ixlib=rb-4.0.3&auto=format&fit=crop&w=1350&q=80" alt="Sound Design">
                    <div class="portfolio-overlay">
                        <span class="category">Audio</span>
                        <h3>Sound Design</h3>
                        <p>Foley and audio effects creation for media</p>
                    </div>
                </div>
                
                <!-- Image Items -->
                <div class="portfolio-item" data-category="image">
                    <img src="https://images.unsplash.com/photo-1506905925346-21bda4d32df4?ixlib=rb-4.0.3&auto=format&fit=crop&w=1350&q=80" alt="Urban Photography">
                    <div class="portfolio-overlay">
                        <span class="category">Visual</span>
                        <h3>Urban Photography</h3>
                        <p>Cityscape and street photography series</p>
                    </div>
                </div>
                
                <div class="portfolio-item" data-category="image">
                    <img src="https://images.unsplash.com/photo-1550745165-9bc0b252726f?ixlib=rb-4.0.3&auto=format&fit=crop&w=1350&q=80" alt="Digital Art">
                    <div class="portfolio-overlay">
                        <span class="category">Visual</span>
                        <h3>Digital Illustration</h3>
                        <p>Concept art and character design</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section class="about" id="about">
        <div class="container">
            <div class="section-title">
                <h2>About Me</h2>
                <p>Get to know the creative mind behind FiraTech</p>
            </div>
            
            <div class="about-content">
                <div class="about-text">
                    <h2>Multimedia Creator & Innovator</h2>
                    <p>I'm a passionate multimedia creator with expertise in video production, audio engineering, and visual design. With years of experience across various platforms, I bring a unique perspective to every project.</p>
                    <p>My work is characterized by attention to detail, innovative approaches, and a commitment to pushing creative boundaries. I believe in the power of multimedia to tell compelling stories and create immersive experiences.</p>
                    
                    <div class="skills">
                        <div class="skill-item">
                            <div class="skill-info">
                                <span>Video Production</span>
                                <span>95%</span>
                            </div>
                            <div class="skill-bar">
                                <div class="skill-progress" style="width: 95%"></div>
                            </div>
                        </div>
                        
                        <div class="skill-item">
                            <div class="skill-info">
                                <span>Audio Engineering</span>
                                <span>90%</span>
                            </div>
                            <div class="skill-bar">
                                <div class="skill-progress" style="width: 90%"></div>
                            </div>
                        </div>
                        
                        <div class="skill-item">
                            <div class="skill-info">
                                <span>Visual Design</span>
                                <span>85%</span>
                            </div>
                            <div class="skill-bar">
                                <div class="skill-progress" style="width: 85%"></div>
                            </div>
                        </div>
                        
                        <div class="skill-item">
                            <div class="skill-info">
                                <span>Content Strategy</span>
                                <span>80%</span>
                            </div>
                            <div class="skill-bar">
                                <div class="skill-progress" style="width: 80%"></div>
                            </div>
                        </div>
                    </div>
                    
                    <a href="#contact" class="glow-btn" style="margin-top: 30px;">Collaborate With Me</a>
                </div>
                
                <div class="about-image">
                    <img src="https://images.unsplash.com/photo-1545235617-9465d2a55698?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="About FiraTech">
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact" id="contact">
        <div class="container">
            <div class="section-title">
                <h2>Get In Touch</h2>
                <p>Let's create something amazing together</p>
            </div>
            
            <div class="contact-content">
                <div class="contact-info">
                    <h3>Connect With Me</h3>
                    <p>Have a project in mind? Want to discuss creative possibilities? Reach out through any of my platforms or send me a message directly.</p>
                    
                    <div class="contact-details">
                        <div class="contact-detail">
                            <i class="fas fa-envelope"></i>
                            <div>
                                <h4>Email</h4>
                                <p>firathech@gmail.com</p>
                            </div>
                        </div>
                        
                        <div class="contact-detail">
                            <i class="fas fa-map-marker-alt"></i>
                            <div>
                                <h4>Based In</h4>
                                <p>Digital Space</p>
                            </div>
                        </div>
                    </div>
                    
                    <h3>Follow My Work</h3>
                    <div class="social-links">
                        <a href="https://t.me/firatech2025" class="social-link" target="_blank">
                            <i class="fab fa-telegram"></i>
                        </a>
                        <a href="http://www.youtube.com/@Firatech2025" class="social-link" target="_blank">
                            <i class="fab fa-youtube"></i>
                        </a>
                        <a href="https://www.tiktok.com/@fira2025t?_t=ZM-90YIrDAwFMC&_r=1" class="social-link" target="_blank">
                            <i class="fab fa-tiktok"></i>
                        </a>
                        <a href="https://www.instagram.com/black_hatpp?igsh=MXhxaGhsd3g3OHI5aA==" class="social-link" target="_blank">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="https://www.facebook.com/share/177wkFQnGZ/" class="social-link" target="_blank">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                    </div>
                </div>
                
                <div class="contact-form">
                    <form id="contactForm">
                        <div class="form-group">
                            <input type="text" placeholder="Your Name" required>
                        </div>
                        <div class="form-group">
                            <input type="email" placeholder="Your Email" required>
                        </div>
                        <div class="form-group">
                            <input type="text" placeholder="Subject" required>
                        </div>
                        <div class="form-group">
                            <textarea placeholder="Your Message" required></textarea>
                        </div>
                        <button type="submit" class="glow-btn">Send Message</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">Fira<span>Tech</span></div>
                <div class="footer-links">
                    <a href="#home">Home</a>
                    <a href="#portfolio">Portfolio</a>
                    <a href="#about">About</a>
                    <a href="#contact">Contact</a>
                </div>
            </div>
            <div class="copyright">
                <p>&copy; 2023 FiraTech. All Rights Reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Modal for Portfolio Items -->
    <div class="modal" id="portfolioModal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <img id="modalImage" src="" alt="">
            <video id="modalVideo" controls style="display:none;">
                <source src="" type="video/mp4">
            </video>
        </div>
    </div>

    <script>
        // Header scroll effect
        window.addEventListener('scroll', function() {
            const header = document.getElementById('header');
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });

        // Portfolio Filtering
        document.addEventListener('DOMContentLoaded', function() {
            const filterButtons = document.querySelectorAll('.filter-btn');
            const portfolioItems = document.querySelectorAll('.portfolio-item');
            
            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    
                    // Add active class to clicked button
                    this.classList.add('active');
                    
                    const filterValue = this.getAttribute('data-filter');
                    
                    portfolioItems.forEach(item => {
                        if (filterValue === 'all' || item.getAttribute('data-category') === filterValue) {
                            item.style.display = 'block';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            });
            
            // Portfolio Modal
            const modal = document.getElementById('portfolioModal');
            const modalImage = document.getElementById('modalImage');
            const modalVideo = document.getElementById('modalVideo');
            const closeModal = document.querySelector('.close-modal');
            
            portfolioItems.forEach(item => {
                item.addEventListener('click', function() {
                    const video = this.querySelector('video');
                    const image = this.querySelector('img');
                    
                    if (video) {
                        modalVideo.style.display = 'block';
                        modalImage.style.display = 'none';
                        modalVideo.querySelector('source').src = video.querySelector('source').src;
                        modalVideo.load();
                    } else if (image) {
                        modalImage.style.display = 'block';
                        modalVideo.style.display = 'none';
                        modalImage.src = image.src;
                    }
                    
                    modal.style.display = 'flex';
                });
            });
            
            closeModal.addEventListener('click', function() {
                modal.style.display = 'none';
                modalVideo.pause();
            });
            
            // Close modal when clicking outside content
            window.addEventListener('click', function(event) {
                if (event.target ===