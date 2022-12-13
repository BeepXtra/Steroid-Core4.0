<!doctype html>
<html lang="en">
    <?php $servername = explode(".", $_SERVER['SERVER_NAME']); ?>
    <head>
        <meta charset="utf-8">
        <!--[if IE]><meta http-equiv="X-UA-Compatible" content="IE=edge" /><![endif]-->
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width,initial-scale=1"><!-- Place favicon.ico in the root directory -->
        <link rel="apple-touch-icon" href="apple-touch-icon.png">
        <link rel="icon" type="image/x-icon" href="img/favicon.ico">
        <link href="https://fonts.googleapis.com/css?family=Poppins:100,300,400,500,700,900" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css?family=Caveat" rel="stylesheet">
        <title><?php echo ucfirst($servername[0]); ?> - Steroid 4</title><!-- themeforest:css -->
        <link rel="stylesheet" href="css/fontawesome.css">
        <link rel="stylesheet" href="css/aos.css">
        <link rel="stylesheet" href="css/cookieconsent.min.css">
        <link rel="stylesheet" href="css/magnific-popup.css">
        <link rel="stylesheet" href="css/odometer-theme-minimal.css">
        <link rel="stylesheet" href="css/prism-okaidia.css">
        <link rel="stylesheet" href="css/simplebar.css">
        <link rel="stylesheet" href="css/smart_wizard_all.css">
        <link rel="stylesheet" href="css/swiper-bundle.css">
        <link rel="stylesheet" href="css/dashcore.css">
        <link rel="stylesheet" href="css/rtl.css">
        <link rel="stylesheet" href="css/demo.css">
        <!-- endinject -->
    </head>

    <body>
        <!--[if lt IE 8]>
    <p class="browserupgrade">You are using an <strong>outdated</strong> browser. Please <a href="http://browsehappy.com/">upgrade your browser</a> to improve your experience.</p>
    <![endif]-->
        <!-- ./Making stripe menu navigation -->
        <?php /*
          <nav class="st-nav navbar main-nav navigation fixed-top" id="main-nav">
          <div class="container">
          <ul class="st-nav-menu nav navbar-nav">
          <li class="st-nav-section st-nav-primary stick-right nav-item"><a class="st-root-link nav-link" href="index.html">Home</a> <a class="st-root-link item-products st-has-dropdown nav-link" data-dropdown="blocks">Blocks</a> <a class="st-root-link item-products st-has-dropdown nav-link" data-dropdown="pages">Pages</a> <a class="st-root-link item-company st-has-dropdown nav-link" data-dropdown="components">UI Components</a> <a class="st-root-link item-blog st-has-dropdown nav-link" data-dropdown="blog">Blog</a> <a class="st-root-link item-shop st-has-dropdown nav-link" href="shop/" data-dropdown="shop">Shop</a></li>
          <li class="st-nav-section st-nav-secondary nav-item"><a class="btn btn-rounded btn-outline me-3 px-3" href="login.html" target="_blank"><i class="fas fa-sign-in-alt d-none d-md-inline me-md-0 me-lg-2"></i> <span class="d-md-none d-lg-inline">Login</span> </a><a class="btn btn-rounded btn-solid px-3" href="signup.html" target="_blank"><i class="fas fa-user-plus d-none d-md-inline me-md-0 me-lg-2"></i> <span class="d-md-none d-lg-inline">Signup</span></a></li><!-- Mobile Navigation -->
          <li class="st-nav-section st-nav-mobile nav-item"><button class="st-root-link navbar-toggler" type="button"><span class="icon-bar"></span> <span class="icon-bar"></span> <span class="icon-bar"></span></button>
          <div class="st-popup">
          <div class="st-popup-container"><a class="st-popup-close-button">Close</a>
          <div class="st-dropdown-content-group">
          <h4 class="text-uppercase regular">Pages</h4><a class="regular text-primary" href="about.html"><i class="far fa-building me-2"></i> About </a><a class="regular text-success" href="contact.html"><i class="far fa-envelope me-2"></i> Contact </a><a class="regular text-warning" href="pricing.html"><i class="fas fa-hand-holding-usd me-2"></i> Pricing </a><a class="regular text-info" href="faqs.html"><i class="far fa-question-circle me-2"></i> FAQs</a>
          </div>
          <div class="st-dropdown-content-group border-top bw-2">
          <h4 class="text-uppercase regular">Components</h4>
          <div class="row">
          <div class="col me-4"><a target="_blank" href="components/alert.html">Alerts</a> <a target="_blank" href="components/badge.html">Badges</a> <a target="_blank" href="components/button.html">Buttons</a> <a target="_blank" href="components/color.html">Colors</a> <a target="_blank" href="components/accordion.html">Accordion</a> <a target="_blank" href="components/cookie-law.html">Cookielaw</a></div>
          <div class="col me-4"><a target="_blank" href="components/overlay.html">Overlay</a> <a target="_blank" href="components/progress.html">Progress</a> <a target="_blank" href="components/lightbox.html">Lightbox</a> <a target="_blank" href="components/tab.html">Tabs</a> <a target="_blank" href="components/tables.html">Tables</a> <a target="_blank" href="components/typography.html">Typography</a></div>
          </div>
          </div>
          <div class="st-dropdown-content-group bg-light b-t"><a href="login.html">Sign in <i class="fas fa-arrow-right"></i></a></div>
          </div>
          </div>
          </li>


          </ul>
          </div>

          <div class="st-dropdown-root">
          <div class="st-dropdown-bg">
          <div class="st-alt-bg"></div>
          </div>
          <div class="st-dropdown-arrow"></div>
          <div class="st-dropdown-container">
          <div class="st-dropdown-section" data-dropdown="blocks">
          <div class="st-dropdown-content">
          <div class="st-dropdown-content-group">
          <div class="row">
          <div class="col me-4"><a class="dropdown-item" target="_blank" href="blocks/call-to-action.html">Call to actions</a> <a class="dropdown-item" target="_blank" href="blocks/contact.html">Contact</a> <a class="dropdown-item" target="_blank" href="blocks/counter.html">Counters</a> <a class="dropdown-item" target="_blank" href="blocks/faqs.html">FAQs</a></div>
          <div class="col me-4"><a class="dropdown-item" target="_blank" href="blocks/footer.html">Footers</a> <a class="dropdown-item" target="_blank" href="blocks/form.html">Forms</a> <a class="dropdown-item" target="_blank" href="blocks/navbar.html">Navbar</a> <a class="dropdown-item" target="_blank" href="blocks/navigation.html">Navigation</a></div>
          <div class="col"><a class="dropdown-item" target="_blank" href="blocks/pricing.html">Pricing</a> <a class="dropdown-item" target="_blank" href="blocks/slider.html">Sliders</a> <a class="dropdown-item" target="_blank" href="blocks/team.html">Team</a> <a class="dropdown-item" target="_blank" href="blocks/testimonial.html">Testimonials</a></div>
          </div>
          </div>
          <div class="st-dropdown-content-group">
          <h3 class="link-title"><i class="fas fa-long-arrow-alt-right icon"></i> Coming soon</h3>
          <div class="ms-5"><span class="dropdown-item text-secondary">Dividers </span><span class="dropdown-item text-secondary">Gallery </span><span class="dropdown-item text-secondary">Screenshots</span></div>
          </div>
          </div>
          </div>
          <div class="st-dropdown-section" data-dropdown="pages">
          <div class="st-dropdown-content">
          <div class="st-dropdown-content-group">
          <div class="mb-4">
          <h3 class="text-darker light text-nowrap"><span class="bold regular">Useful pages</span> you'll need</h3>
          <p class="text-secondary mt-0">Get a complete design stack</p>
          </div>
          <div class="row">
          <div class="col">
          <ul class="me-4">
          <li>
          <h4 class="text-uppercase regular">Error</h4>
          </li>
          <li><a target="_blank" href="403.html">403 Error</a></li>
          <li><a target="_blank" href="404.html">404 Error</a></li>
          <li><a target="_blank" href="500.html">500 Error</a></li>
          </ul>
          </div>
          <div class="col">
          <ul class="me-4">
          <li>
          <h4 class="text-uppercase regular">User</h4>
          </li>
          <li><a target="_blank" href="login.html">Login</a></li>
          <li><a target="_blank" href="register.html">Register</a></li>
          <li><a target="_blank" href="forgot.html">Forgot</a></li>
          </ul>
          </div>
          <div class="col">
          <ul>
          <li>
          <h4 class="text-uppercase regular">Extra</h4>
          </li>
          <li><a target="_blank" href="pricing.html">Pricing</a></li>
          <li><a target="_blank" href="terms.html">Terms</a></li>
          <li><a target="_blank" href="faqs.html">FAQ</a></li>
          </ul>
          </div>
          </div>
          </div>
          <div class="st-dropdown-content-group"><a class="dropdown-item bold" href="about.html"><i class="far fa-building icon"></i> About </a><a class="dropdown-item bold" href="contact.html"><i class="far fa-envelope icon"></i> Contact </a><a class="dropdown-item bold" href="pricing.html"><i class="fas fa-hand-holding-usd icon"></i> Pricing</a></div>
          </div>
          </div>
          <div class="st-dropdown-section" data-dropdown="components">
          <div class="st-dropdown-content">
          <div class="st-dropdown-content-group"><a class="dropdown-item" target="_blank" href="components/color.html">
          <div class="d-flex align-items-center mb-3">
          <div class="bg-dark text-contrast icon-md center-flex rounded-circle me-2"><i class="fas fa-palette"></i></div>
          <div class="flex-fill">
          <h3 class="link-title m-0">Colors</h3>
          <p class="m-0 text-secondary">Get to know DashCore color options</p>
          </div>
          </div>
          </a><a class="dropdown-item" target="_blank" href="components/form-controls.html">
          <div class="d-flex align-items-center mb-3">
          <div class="bg-secondary text-contrast icon-md center-flex rounded-circle me-2"><i class="fab fa-wpforms"></i></div>
          <div class="flex-fill">
          <h3 class="link-title m-0">Forms</h3>
          <p class="m-0 text-secondary">All forms elements</p>
          </div>
          </div>
          </a><a class="dropdown-item" target="_blank" href="components/accordion.html">
          <div class="d-flex align-items-center mb-3">
          <div class="bg-success text-contrast icon-md center-flex rounded-circle me-2"><i class="fas fa-bars"></i></div>
          <div class="flex-fill">
          <h3 class="link-title m-0">Accordion</h3>
          <p class="m-0 text-secondary">Useful accordion elements</p>
          </div>
          </div>
          </a><a class="dropdown-item" target="_blank" href="components/cookie-law.html">
          <div class="d-flex align-items-center mb-4">
          <div class="bg-info text-contrast icon-md center-flex rounded-circle me-2"><i class="fas fa-cookie-bite"></i></div>
          <div class="flex-fill">
          <h3 class="link-title m-0">CookieLaw</h3>
          <p class="m-0 text-secondary">Comply with the hideous EU Cookie Law</p>
          </div>
          </div>
          </a>
          <h4 class="text-uppercase regular">Huge components list</h4>
          <div class="row">
          <div class="col me-4"><a class="dropdown-item" target="_blank" href="components/alert.html">Alerts</a> <a class="dropdown-item" target="_blank" href="components/badge.html">Badges</a> <a class="dropdown-item" target="_blank" href="components/button.html">Buttons</a></div>
          <div class="col me-4"><a class="dropdown-item" target="_blank" href="components/overlay.html">Overlay</a> <a class="dropdown-item" target="_blank" href="components/progress.html">Progress</a> <a class="dropdown-item" target="_blank" href="components/lightbox.html">Lightbox</a></div>
          <div class="col me-4"><a class="dropdown-item" target="_blank" href="components/tab.html">Tabs</a> <a class="dropdown-item" target="_blank" href="components/tables.html">Tables</a> <a class="dropdown-item" target="_blank" href="components/typography.html">Typography</a></div>
          </div>
          </div>
          <div class="st-dropdown-content-group"><a class="dropdown-item" target="_blank" href="components/wizard.html">Wizard </a><span class="dropdown-item d-flex align-items-center text-muted">Timeline <i class="fas fa-ban ms-auto"></i> </span><span class="dropdown-item d-flex align-items-center text-muted">Process <i class="fas fa-ban ms-auto"></i></span></div>
          </div>
          </div>
          <div class="st-dropdown-section" data-dropdown="blog">
          <div class="st-dropdown-content">
          <div class="st-dropdown-content-group">
          <div class="row">
          <div class="col me-4">
          <h4 class="regular text-uppercase">Full width</h4><a class="dropdown-item" target="_blank" href="blog/blog-post.html">Single post</a> <a class="dropdown-item" target="_blank" href="blog/blog-grid.html">Posts Grid</a>
          </div>
          <div class="col me-4">
          <h4 class="regular text-uppercase">Sidebar left</h4><a class="dropdown-item" target="_blank" href="blog/blog-post-sidebar-left.html">Single post</a> <a class="dropdown-item" target="_blank" href="blog/blog-grid-sidebar-left.html">Posts Grid</a>
          </div>
          <div class="col me-4">
          <h4 class="regular text-uppercase">Sidebar right</h4><a class="dropdown-item" target="_blank" href="blog/blog-post-sidebar-right.html">Single post</a> <a class="dropdown-item" target="_blank" href="blog/blog-grid-sidebar-right.html">Posts Grid</a>
          </div>
          </div>
          </div>
          </div>
          </div>
          <div class="st-dropdown-section" data-dropdown="shop">
          <div class="st-dropdown-content">
          <div class="st-dropdown-content-group"><a class="dropdown-item mb-4" target="_blank" href="shop/">
          <div class="d-flex align-items-center">
          <div class="bg-success text-contrast icon-md center-flex rounded-circle me-2"><i class="fas fa-shopping-basket"></i></div>
          <div class="flex-fill">
          <h3 class="link-title m-0">Home</h3>
          <p class="m-0 text-secondary">Online store home with an outstanding UX</p>
          </div>
          </div>
          </a><a class="dropdown-item" target="_blank" href="shop/cart.html">
          <div class="d-flex align-items-center">
          <div class="bg-info text-contrast icon-md center-flex rounded-circle me-2"><i class="fas fa-shopping-cart"></i></div>
          <div class="flex-fill">
          <h3 class="link-title m-0">Cart</h3>
          <p class="m-0 text-secondary">Online store shopping cart</p>
          </div>
          </div>
          </a></div>
          <div class="st-dropdown-content-group">
          <h3 class="link-title"><i class="fas fa-money-check-alt icon"></i> Checkout</h3>
          <div class="ms-5"><a class="dropdown-item text-secondary" target="_blank" href="shop/checkout-customer.html">Customer <i class="fas fa-angle-right ms-2"></i> </a><a class="dropdown-item text-secondary" target="_blank" href="shop/checkout-shipping.html">Shipping Information <i class="fas fa-angle-right ms-2"></i> </a><a class="dropdown-item text-secondary" target="_blank" href="shop/checkout-payment.html">Payment Methods <i class="fas fa-angle-right ms-2"></i> </a><a class="dropdown-item text-secondary" target="_blank" href="shop/checkout-confirmation.html">Order Review <i class="fas fa-angle-right ms-2"></i></a></div>
          </div>
          </div>
          </div>
          </div>

          </div>

          </nav>
         */ ?>
        <main class="overflow-hidden">
            <!-- ./Page header -->
            <header class="section header smart-business-header">
                <?php //st-nav navbar main-nav navigation ?>


                <div class="shape-wrapper">
                    <div class="shape-background shape-top center-xy"></div>
                    <div class="shape-background shape-right"></div><!-- main shape -->
                    <div class="background-shape"></div>
                </div>
                <?php
                $options = array('http' => array('user_agent' => 'custom user agent string'));
                $context = stream_context_create($options);
                $masternodedetails = json_decode(file_get_contents('https://' . $servername[0] . '.steroid.io/api/node-info', false, $context));
                ?>
                <div class="container">

                    <div style="margin-top: -50px;">
                        <a href="https://steroid.io" class="navbar-brand st-nav-section nav-item"><img src="img/steroidlogo.png" alt="Steroid" class="logo logo-sticky"></a>
                    </div>

                    <div class="row gap-y">
                        <div class="col-md-7">
                            <p class="regular small text-uppercase text-secondary">Welcome to Steroid 4</p>
                            <h1 class="extra-bold display-md-3 font-md"><?php echo ucfirst($servername[0]); ?> <span class="d-block light">Decentralized World</span></h1>
                            <p class="lead">This is the <?php echo ucfirst($servername[0]); ?> masternode.</p>

                            <nav class="mt-5">

                                <p class="me-3 bw-2 p-1"><span class="bg-success text-white p-2">Current block</span> <span class="text-light bg-dark p-2"><?php echo $masternodedetails->data->data->height ?> </span></p>

                                <p class="me-3 bw-2 p-1"><span class="bg-warning text-white p-2">Version</span> <span class="text-light bg-dark p-2"><?php echo $masternodedetails->data->data->version ?> </span></p>

                                <p class="me-3 bw-2 p-1"><span class="bg-info text-white p-2">DB version</span> <span class="text-light bg-dark p-2"><?php echo $masternodedetails->data->data->dbversion ?> </span></p>

                                <p class="me-3 bw-2 p-1"><span class="bg-secondary text-white p-2">Transactions</span> <span class="text-light bg-dark p-2"><?php echo $masternodedetails->data->data->transactions ?> </span></p>

                                <p class="me-3 bw-2 p-1"><span class="bg-danger text-white p-2">Accounts</span> <span class="text-light bg-dark p-2"><?php echo $masternodedetails->data->data->accounts ?> </span></p>

                            </nav>

                            <nav class=" mt-5">
                                <a href="https://steroid.io/" class="me-3 btn btn-rounded btn-outline-info bw-2"><i class="fas fa-globe me-3"></i> Website </a>

                                <a href="https://explorer.steroid.io/" class="me-3 btn btn-rounded btn-outline-secondary bw-2"><i class="fas fa-search me-3"></i> Explorer </a>

                                <a href="https://peer1.steroid.io/doc/index.html" class="me-3  btn  btn-rounded btn-outline-primary bw-2"><i class="fas fa-book me-3"></i> Documentation </a>

                                <a href="https://www.beepxtra.com/" class="me-3  btn btn-rounded btn-outline-danger bw-2"><i class="fas fa-blog me-3"></i> BeepXtra </a>
                            </nav>
                        </div>
                    </div>
                </div>
                <div class="main-shape-wrapper">
                    <div data-aos="fade-left" data-aos-delay="300"><img src="img/smart-business/header/main-shape.svg" class="img-responsive main-shape" alt="steroidbackground"> <img src="img/3dsteroidlogo2.png" class="anim anim-1 floating" alt="steroid3dlogo"> <img src="img/3dsteroidlogo2.png" class="anim anim-2 floating" alt="steroid3dlogo"> <img src="img/3dsteroidlogo2.png" class="anim anim-3 floating" alt="steroid3dlogo"></div>
                </div>
            </header><!-- Features Carousel -->
            <?php /*
              <section class="section features-carousel b-b">
              <div class="container pt-0">
              <div class="swiper-container" data-sw-autoplay="3500" data-sw-loop="true" data-sw-nav-arrows=".features-nav" data-sw-show-items="1" data-sw-space-between="30" data-sw-breakpoints='{"768": {"slidesPerView": 3}, "992": {"slidesPerView": 4}}'>
              <div class="swiper-wrapper px-1">
              <div class="swiper-slide px-2 px-sm-1">
              <div class="card border-0 shadow">
              <div class="card-body">
              <div class="rounded-circle bg-light p-3 d-flex align-items-center justify-content-center shadow icon-xl"><img src="img/smart-business/icons/chat.svg" class="img-responsive" alt=""></div>
              <h4 class="mt-4">Social<br><span class="bold">Integration</span></h4>
              <p>Vulputate mi habitant curae; per facilisis. Ornare. Imperdiet curabitur, enim venenatis donec consequat adipiscing.</p>
              </div>
              </div>
              </div>
              <div class="swiper-slide px-2 px-sm-1">
              <div class="card border-0 shadow">
              <div class="card-body">
              <div class="rounded-circle bg-light p-3 d-flex align-items-center justify-content-center shadow icon-xl"><img src="img/smart-business/icons/strategy.svg" class="img-responsive" alt=""></div>
              <h4 class="mt-4">Design<br><span class="bold">Strategy</span></h4>
              <p>Vulputate mi habitant curae; per facilisis. Ornare. Imperdiet curabitur, enim venenatis donec consequat adipiscing.</p>
              </div>
              </div>
              </div>
              <div class="swiper-slide px-2 px-sm-1">
              <div class="card border-0 shadow">
              <div class="card-body">
              <div class="rounded-circle bg-light p-3 d-flex align-items-center justify-content-center shadow icon-xl"><img src="img/smart-business/icons/money.svg" class="img-responsive" alt=""></div>
              <h4 class="mt-4">Save<br><span class="bold">Money</span></h4>
              <p>Vulputate mi habitant curae; per facilisis. Ornare. Imperdiet curabitur, enim venenatis donec consequat adipiscing.</p>
              </div>
              </div>
              </div>
              <div class="swiper-slide px-2 px-sm-1">
              <div class="card border-0 shadow">
              <div class="card-body">
              <div class="rounded-circle bg-light p-3 d-flex align-items-center justify-content-center shadow icon-xl"><img src="img/smart-business/icons/user.svg" class="img-responsive" alt=""></div>
              <h4 class="mt-4">Business<br><span class="bold">Brain</span></h4>
              <p>Vulputate mi habitant curae; per facilisis. Ornare. Imperdiet curabitur, enim venenatis donec consequat adipiscing.</p>
              </div>
              </div>
              </div>
              <div class="swiper-slide px-2 px-sm-1">
              <div class="card border-0 shadow">
              <div class="card-body">
              <div class="rounded-circle bg-light p-3 d-flex align-items-center justify-content-center shadow icon-xl"><img src="img/smart-business/icons/worldwide.svg" class="img-responsive" alt=""></div>
              <h4 class="mt-4">Worldwide<br><span class="bold">Support</span></h4>
              <p>Vulputate mi habitant curae; per facilisis. Ornare. Imperdiet curabitur, enim venenatis donec consequat adipiscing.</p>
              </div>
              </div>
              </div>
              <div class="swiper-slide px-2 px-sm-1">
              <div class="card border-0 shadow">
              <div class="card-body">
              <div class="rounded-circle bg-light p-3 d-flex align-items-center justify-content-center shadow icon-xl"><img src="img/smart-business/icons/like.svg" class="img-responsive" alt=""></div>
              <h4 class="mt-4">Social<br><span class="bold">Settings</span></h4>
              <p>Vulputate mi habitant curae; per facilisis. Ornare. Imperdiet curabitur, enim venenatis donec consequat adipiscing.</p>
              </div>
              </div>
              </div>
              <div class="swiper-slide px-2 px-sm-1">
              <div class="card border-0 shadow">
              <div class="card-body">
              <div class="rounded-circle bg-light p-3 d-flex align-items-center justify-content-center shadow icon-xl"><img src="img/smart-business/icons/graph.svg" class="img-responsive" alt=""></div>
              <h4 class="mt-4">Insightful<br><span class="bold">Statistics</span></h4>
              <p>Vulputate mi habitant curae; per facilisis. Ornare. Imperdiet curabitur, enim venenatis donec consequat adipiscing.</p>
              </div>
              </div>
              </div>
              </div><!-- Add Arrows -->
              <div class="text-primary features-nav features-nav-next"><span class="text-uppercase small">Next</span> <i class="features-nav-icon fas fa-long-arrow-alt-right"></i></div>
              </div>
              </div>
              </section><!-- ./Why Us -->
              <section class="section">
              <div class="container">
              <div class="row gap-y align-items-center">
              <div class="col-md-6">
              <div class="section-heading">
              <p class="text-primary bold small text-uppercase">some reasons</p>
              <h2 class="bold">Why Choose DashCore?</h2>
              </div>
              <p class="regular">Lorem ipsum dolor sit amet, consectetur adipisicing elit. Ab adipisci, architecto asperiores dignissimos doloribus dolorum eos esse eum laborum minima molestias, natus nostrum odio quia recusandae rem sequi similique velit.</p><a href="javascript:;" class="btn btn-outline-primary btn-rounded bw-2 mt-4">Read More</a>
              </div>
              <div class="col-md-6">
              <div class="animate-bars">
              <ul class="progress-bars whyus-progress-bars"></ul>
              </div>
              </div>
              </div>
              <div class="row gap-y pt-5">
              <div class="col-6 col-md-3">
              <div class="d-flex align-items-center"><i data-feather="box" width="36" height="36" class="stroke-primary me-2"></i> <span class="counter bold text-darker font-md">273</span></div>
              <p class="text-secondary m-0">Components</p>
              </div>
              <div class="col-6 col-md-3">
              <div class="d-flex align-items-center"><i data-feather="download-cloud" width="36" height="36" class="stroke-primary me-2"></i> <span class="counter bold text-darker font-md">654</span></div>
              <p class="text-secondary m-0">Downloads</p>
              </div>
              <div class="col-6 col-md-3">
              <div class="d-flex align-items-center"><i data-feather="sliders" width="36" height="36" class="stroke-primary me-2"></i> <span class="counter bold text-darker font-md">7941</span></div>
              <p class="text-secondary m-0">Followers</p>
              </div>
              <div class="col-6 col-md-3">
              <div class="d-flex align-items-center"><i data-feather="award" width="36" height="36" class="stroke-primary me-2"></i> <span class="counter bold text-darker font-md">654</span></div>
              <p class="text-secondary m-0">New users</p>
              </div>
              </div>
              </div>
              </section>
              <div class="position-relative">
              <div class="shape-divider shape-divider-bottom shape-divider-fluid-x text-primary"><svg viewBox="0 0 2880 48" xmlns="http://www.w3.org/2000/svg">
              <path d="M0 48H1437.5H2880V0H2160C1442.5 52 720 0 720 0H0V48Z"></path>
              </svg></div>
              </div><!-- You deserve better -->
              <section class="section bg-primary">
              <div class="container text-center">
              <div class="section-heading">
              <h2 class="bold text-contrast">You deserve better</h2>
              <p class="lead text-light">With DashCore you will not only get a beautiful HTML template tou showoff your web, but a complete starter kit to bring your application to life right away</p>
              </div>
              </div>
              </section><!-- Powerful Tools -->
              <section class="section">
              <div class="container mt-n9">
              <div class="row gap-y">
              <div class="col-md-6">
              <div class="rounded media bg-contrast shadow-lg p-4 lift-hover">
              <div class="shadow bg-primary text-contrast rounded-circle p-3 icon-xl mb-3 d-flex align-items-center justify-content-center" data-aos="zoom-in"><i class="far fa-paper-plane fa-2x"></i></div>
              <h5 class="bold text-capitalize">easy to integrate</h5>
              <p class="text-secondary mb-0">Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium consectetur adipisicing elit.</p>
              </div>
              </div>
              <div class="col-md-6">
              <div class="rounded media bg-contrast shadow-lg p-4 lift-hover">
              <div class="shadow bg-primary text-contrast rounded-circle p-3 icon-xl mb-3 d-flex align-items-center justify-content-center" data-aos="zoom-in"><i class="far fa-heart fa-2x"></i></div>
              <h5 class="bold text-capitalize">seamlessly solution</h5>
              <p class="text-secondary mb-0">Ut enim ad minima veniam, quis nostrum voluptatem accusantium ullam corporis obcaecati optio quasi qui.</p>
              </div>
              </div>
              </div>
              </div>
              </section><!-- ./Partners -->
              <section class="partners">
              <div class="container pt-4">
              <div class="swiper-container" data-sw-show-items="5" data-sw-space-between="30" data-sw-autoplay="2500">
              <div class="swiper-wrapper align-items-center">
              <div class="swiper-slide"><img src="img/logos/1.png" class="img-responsive" alt="" style="max-height: 60px"></div>
              <div class="swiper-slide"><img src="img/logos/2.png" class="img-responsive" alt="" style="max-height: 60px"></div>
              <div class="swiper-slide"><img src="img/logos/3.png" class="img-responsive" alt="" style="max-height: 60px"></div>
              <div class="swiper-slide"><img src="img/logos/4.png" class="img-responsive" alt="" style="max-height: 60px"></div>
              <div class="swiper-slide"><img src="img/logos/5.png" class="img-responsive" alt="" style="max-height: 60px"></div>
              <div class="swiper-slide"><img src="img/logos/6.png" class="img-responsive" alt="" style="max-height: 60px"></div>
              </div>
              </div>
              </div>
              </section><!-- Extend Core -->
              <section class="section extending-core border-top bg-light edge bottom-right">
              <div class="shapes-container">
              <div class="shape shape-circle">
              <div></div>
              </div>
              </div>
              <div class="container">
              <div class="row gap-y align-items-center">
              <div class="col-lg-6">
              <div class="section-heading">
              <p class="text-primary bold small text-uppercase">enterprise integration</p>
              <h2 class="bold">Extend DashCore</h2>
              <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit. Excepturi ipsum iste iure nihil non obcaecati quasi, sit? Aperiam asperiores atque, commodi debitis fugiat in nemo optio sint velit. Pariatur, sint!</p>
              </div><a href="#" class="btn btn-rounded btn-outline-primary bw-2 me-3">Know More</a> <a href="#" class="btn btn-rounded btn-primary bw-2 bold text-contrast">Register Account</a>
              </div>
              <div class="col-lg-6">
              <div class="icons-wrapper position-relative">
              <div class="floating icon icon-xl bg-contrast rounded-circle p-3 shadow m-0 absolute d-flex justify-content-center align-items-center" style="left:100%; top: 30%;" data-aos="fade-left"><img src="img/integration/blossom.svg" class="img-responsive" alt=""></div>
              <div class="floating icon icon-xxl bg-contrast rounded-circle p-3 shadow m-0 absolute d-flex justify-content-center align-items-center" style="left:60%; top: -10%;" data-aos="fade-left"><img src="img/integration/dockbit.svg" class="img-responsive" alt=""></div>
              <div class="floating icon icon-xxl bg-contrast rounded-circle p-3 shadow m-0 absolute d-flex justify-content-center align-items-center" style="left:25%; top: 0%;" data-aos="fade-left"><img src="img/integration/zapier.svg" class="img-responsive" alt=""></div>
              <div class="floating icon icon-2xl bg-contrast rounded-circle p-3 shadow m-0 absolute d-flex justify-content-center align-items-center" style="left:0%; top: 50%;" data-aos="fade-left"><img src="img/integration/bitnami.svg" class="img-responsive" alt=""></div>
              <div class="floating icon icon-2xxl bg-contrast rounded-circle p-3 shadow m-0 absolute d-flex justify-content-center align-items-center" style="left:23%; top: 70%;" data-aos="fade-left"><img src="img/integration/slack.svg" class="img-responsive" alt=""></div>
              <div class="floating icon icon-xxl bg-contrast rounded-circle p-3 shadow m-0 absolute d-flex justify-content-center align-items-center" style="left:65%; top: 44%;" data-aos="fade-left"><img src="img/integration/monero.svg" class="img-responsive" alt=""></div>
              <div class="floating icon icon-xl bg-contrast rounded-circle p-3 shadow m-0 absolute d-flex justify-content-center align-items-center" style="left:95%; top: 83%;" data-aos="fade-left"><img src="img/integration/dropbox.svg" class="img-responsive" alt=""></div>
              </div>
              </div>
              </div>
              </div>
              </section><!-- User Reviews -->
              <section class="section singl-testimonial">
              <div class="container pt-8 bring-to-front">
              <div class="swiper-container pb-0 pb-lg-8" data-sw-nav-arrows=".reviews-nav">
              <div class="swiper-wrapper">
              <div class="swiper-slide">
              <div class="row gap-y align-items-center">
              <div class="col-lg-6">
              <figure class="testimonial-img ms-md-auto"><img src="img/smart-business/reviews/1.jpg" class="img-responsive rounded shadow-lg" alt="..."></figure>
              </div>
              <div class="col-lg-6 ms-md-auto">
              <div class="user-review text-center italic bg-primary text-contrast rounded shadow-lg py-5 px-4 px-lg-6">
              <blockquote class="regular py-4"><i class="fas fa-quote-left"></i> Lorem ipsum dolor sit amet, consectetur adipisicing elit. Aliquid amet aspernatur, autem deserunt distinctio dolores eius, exercitationem facilis inventore. <i class="fas fa-quote-right"></i></blockquote>
              <div class="author mt-4">
              <p class="small"><span class="bold text-contrast">Jane Doe,</span> Web Developer</p><img src="img/smart-business/reviews/signature.svg" class="img-responsive signature mx-auto" alt="...">
              </div>
              <div class="shape-wrapper" data-aos="fade-up"><svg class="svg-review-bottom" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none">
              <path d="M95,0 Q90,90 0,100 L100,100 100,0 Z" fill="#4f2ca9"></path>
              </svg> <svg class="svg-review-bottom back" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none">
              <path d="M95,0 Q90,90 0,100 L100,100 100,0 Z" fill="#8053ff"></path>
              </svg> <svg class="svg-review-bottom back left" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none">
              <path d="M95,0 Q90,90 0,100 L100,100 100,0 Z" fill="#3f179a"></path>
              </svg></div>
              </div>
              </div>
              </div>
              </div>
              <div class="swiper-slide">
              <div class="row gap-y align-items-center">
              <div class="col-lg-6">
              <figure class="testimonial-img ms-md-auto"><img src="img/smart-business/reviews/2.jpg" class="img-responsive rounded shadow-lg" alt="..."></figure>
              </div>
              <div class="col-lg-6 ms-md-auto">
              <div class="user-review text-center italic bg-primary text-contrast rounded shadow-lg py-5 px-4 px-lg-6">
              <blockquote class="regular py-4"><i class="fas fa-quote-left"></i> Lorem ipsum dolor sit amet, consectetur adipisicing elit. Aliquid amet aspernatur, autem deserunt distinctio dolores eius, exercitationem facilis inventore. <i class="fas fa-quote-right"></i></blockquote>
              <div class="author mt-4">
              <p class="small"><span class="bold text-contrast">Lorem Team,</span> Web Developer</p><img src="img/smart-business/reviews/signature.svg" class="img-responsive signature mx-auto" alt="...">
              </div>
              <div class="shape-wrapper" data-aos="fade-up"><svg class="svg-review-bottom" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none">
              <path d="M95,0 Q90,90 0,100 L100,100 100,0 Z" fill="#4f2ca9"></path>
              </svg> <svg class="svg-review-bottom back" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none">
              <path d="M95,0 Q90,90 0,100 L100,100 100,0 Z" fill="#8053ff"></path>
              </svg> <svg class="svg-review-bottom back left" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none">
              <path d="M95,0 Q90,90 0,100 L100,100 100,0 Z" fill="#3f179a"></path>
              </svg></div>
              </div>
              </div>
              </div>
              </div>
              <div class="swiper-slide">
              <div class="row gap-y align-items-center">
              <div class="col-lg-6">
              <figure class="testimonial-img ms-md-auto"><img src="img/smart-business/reviews/3.jpg" class="img-responsive rounded shadow-lg" alt="..."></figure>
              </div>
              <div class="col-lg-6 ms-md-auto">
              <div class="user-review text-center italic bg-primary text-contrast rounded shadow-lg py-5 px-4 px-lg-6">
              <blockquote class="regular py-4"><i class="fas fa-quote-left"></i> Lorem ipsum dolor sit amet, consectetur adipisicing elit. Aliquid amet aspernatur, autem deserunt distinctio dolores eius, exercitationem facilis inventore. <i class="fas fa-quote-right"></i></blockquote>
              <div class="author mt-4">
              <p class="small"><span class="bold text-contrast">Ipsum Team,</span> Web Developer</p><img src="img/smart-business/reviews/signature.svg" class="img-responsive signature mx-auto" alt="...">
              </div>
              <div class="shape-wrapper" data-aos="fade-up"><svg class="svg-review-bottom" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none">
              <path d="M95,0 Q90,90 0,100 L100,100 100,0 Z" fill="#4f2ca9"></path>
              </svg> <svg class="svg-review-bottom back" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none">
              <path d="M95,0 Q90,90 0,100 L100,100 100,0 Z" fill="#8053ff"></path>
              </svg> <svg class="svg-review-bottom back left" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none">
              <path d="M95,0 Q90,90 0,100 L100,100 100,0 Z" fill="#3f179a"></path>
              </svg></div>
              </div>
              </div>
              </div>
              </div>
              <div class="swiper-slide">
              <div class="row gap-y align-items-center">
              <div class="col-lg-6">
              <figure class="testimonial-img ms-md-auto"><img src="img/smart-business/reviews/4.jpg" class="img-responsive rounded shadow-lg" alt="..."></figure>
              </div>
              <div class="col-lg-6 ms-md-auto">
              <div class="user-review text-center italic bg-primary text-contrast rounded shadow-lg py-5 px-4 px-lg-6">
              <blockquote class="regular py-4"><i class="fas fa-quote-left"></i> Lorem ipsum dolor sit amet, consectetur adipisicing elit. Aliquid amet aspernatur, autem deserunt distinctio dolores eius, exercitationem facilis inventore. <i class="fas fa-quote-right"></i></blockquote>
              <div class="author mt-4">
              <p class="small"><span class="bold text-contrast">Priscilla Campbell,</span> Web Developer</p><img src="img/smart-business/reviews/signature.svg" class="img-responsive signature mx-auto" alt="...">
              </div>
              <div class="shape-wrapper" data-aos="fade-up"><svg class="svg-review-bottom" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none">
              <path d="M95,0 Q90,90 0,100 L100,100 100,0 Z" fill="#4f2ca9"></path>
              </svg> <svg class="svg-review-bottom back" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none">
              <path d="M95,0 Q90,90 0,100 L100,100 100,0 Z" fill="#8053ff"></path>
              </svg> <svg class="svg-review-bottom back left" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none">
              <path d="M95,0 Q90,90 0,100 L100,100 100,0 Z" fill="#3f179a"></path>
              </svg></div>
              </div>
              </div>
              </div>
              </div>
              <div class="swiper-slide">
              <div class="row gap-y align-items-center">
              <div class="col-lg-6">
              <figure class="testimonial-img ms-md-auto"><img src="img/smart-business/reviews/5.jpg" class="img-responsive rounded shadow-lg" alt="..."></figure>
              </div>
              <div class="col-lg-6 ms-md-auto">
              <div class="user-review text-center italic bg-primary text-contrast rounded shadow-lg py-5 px-4 px-lg-6">
              <blockquote class="regular py-4"><i class="fas fa-quote-left"></i> Lorem ipsum dolor sit amet, consectetur adipisicing elit. Aliquid amet aspernatur, autem deserunt distinctio dolores eius, exercitationem facilis inventore. <i class="fas fa-quote-right"></i></blockquote>
              <div class="author mt-4">
              <p class="small"><span class="bold text-contrast">Edith Fisher,</span> Web Developer</p><img src="img/smart-business/reviews/signature.svg" class="img-responsive signature mx-auto" alt="...">
              </div>
              <div class="shape-wrapper" data-aos="fade-up"><svg class="svg-review-bottom" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none">
              <path d="M95,0 Q90,90 0,100 L100,100 100,0 Z" fill="#4f2ca9"></path>
              </svg> <svg class="svg-review-bottom back" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none">
              <path d="M95,0 Q90,90 0,100 L100,100 100,0 Z" fill="#8053ff"></path>
              </svg> <svg class="svg-review-bottom back left" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none">
              <path d="M95,0 Q90,90 0,100 L100,100 100,0 Z" fill="#3f179a"></path>
              </svg></div>
              </div>
              </div>
              </div>
              </div>
              <div class="swiper-slide">
              <div class="row gap-y align-items-center">
              <div class="col-lg-6">
              <figure class="testimonial-img ms-md-auto"><img src="img/smart-business/reviews/6.jpg" class="img-responsive rounded shadow-lg" alt="..."></figure>
              </div>
              <div class="col-lg-6 ms-md-auto">
              <div class="user-review text-center italic bg-primary text-contrast rounded shadow-lg py-5 px-4 px-lg-6">
              <blockquote class="regular py-4"><i class="fas fa-quote-left"></i> Lorem ipsum dolor sit amet, consectetur adipisicing elit. Aliquid amet aspernatur, autem deserunt distinctio dolores eius, exercitationem facilis inventore. <i class="fas fa-quote-right"></i></blockquote>
              <div class="author mt-4">
              <p class="small"><span class="bold text-contrast">Kenneth Reyes,</span> Web Developer</p><img src="img/smart-business/reviews/signature.svg" class="img-responsive signature mx-auto" alt="...">
              </div>
              <div class="shape-wrapper" data-aos="fade-up"><svg class="svg-review-bottom" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none">
              <path d="M95,0 Q90,90 0,100 L100,100 100,0 Z" fill="#4f2ca9"></path>
              </svg> <svg class="svg-review-bottom back" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none">
              <path d="M95,0 Q90,90 0,100 L100,100 100,0 Z" fill="#8053ff"></path>
              </svg> <svg class="svg-review-bottom back left" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none">
              <path d="M95,0 Q90,90 0,100 L100,100 100,0 Z" fill="#3f179a"></path>
              </svg></div>
              </div>
              </div>
              </div>
              </div>
              <div class="swiper-slide">
              <div class="row gap-y align-items-center">
              <div class="col-lg-6">
              <figure class="testimonial-img ms-md-auto"><img src="img/smart-business/reviews/7.jpg" class="img-responsive rounded shadow-lg" alt="..."></figure>
              </div>
              <div class="col-lg-6 ms-md-auto">
              <div class="user-review text-center italic bg-primary text-contrast rounded shadow-lg py-5 px-4 px-lg-6">
              <blockquote class="regular py-4"><i class="fas fa-quote-left"></i> Lorem ipsum dolor sit amet, consectetur adipisicing elit. Aliquid amet aspernatur, autem deserunt distinctio dolores eius, exercitationem facilis inventore. <i class="fas fa-quote-right"></i></blockquote>
              <div class="author mt-4">
              <p class="small"><span class="bold text-contrast">Daniel Hamilton,</span> Web Developer</p><img src="img/smart-business/reviews/signature.svg" class="img-responsive signature mx-auto" alt="...">
              </div>
              <div class="shape-wrapper" data-aos="fade-up"><svg class="svg-review-bottom" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none">
              <path d="M95,0 Q90,90 0,100 L100,100 100,0 Z" fill="#4f2ca9"></path>
              </svg> <svg class="svg-review-bottom back" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none">
              <path d="M95,0 Q90,90 0,100 L100,100 100,0 Z" fill="#8053ff"></path>
              </svg> <svg class="svg-review-bottom back left" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none">
              <path d="M95,0 Q90,90 0,100 L100,100 100,0 Z" fill="#3f179a"></path>
              </svg></div>
              </div>
              </div>
              </div>
              </div>
              </div><!-- Add Arrows -->
              <div class="reviews-navigation">
              <div class="reviews-nav reviews-nav-prev btn btn-gray-light btn-rounded shadow-hover">
              <!-- <span class="text-uppercase small">Next</span> --> <i class="reviews-nav-icon fas fa-long-arrow-alt-left"></i></div>
              <div class="reviews-nav reviews-nav-next btn btn-gray-light btn-rounded shadow-hover">
              <!-- <span class="text-uppercase small">Next</span> --> <i class="reviews-nav-icon fas fa-long-arrow-alt-right"></i></div>
              </div>
              </div>
              </div>
              </section><!-- ./CTA - Create Account -->
              <section class="section bg-light edge top-left">
              <div class="container pt-5">
              <div class="d-flex align-items-center flex-column flex-md-row">
              <div class="text-center text-md-start">
              <p class="light mb-0 text-primary lead">Ready to get started?</p>
              <h2 class="mt-0 bold">Create an account now</h2>
              </div><a href="register.html" class="btn btn-primary btn-rounded mt-3 mt-md-0 ms-md-auto">Create DashCore account</a>
              </div>
              </div>
              </section><!-- ./Footer - Headline -->
             */ ?>

            <footer class="site-footer section bg-darker text-contrast text-center">
                <div class="container"><img src="img/steroidlogo.png" alt="steroidlogo" class="logo">
                    <p class="lead mt-2"><span class="bold">Steroid 4</span></p>
                    <p class="copyright my-2">© 2022 Steroid</p>
                    <?php
                    // <hr class="mt-5 bg-secondary op-5">
                    //<nav class="nav social-icons justify-content-center mt-4"><a href="#" class="btn text-contrast btn-circle btn-sm brand-facebook me-3"><i class="fab fa-facebook"></i></a> <a href="#" class="btn text-contrast btn-circle btn-sm brand-twitter me-3"><i class="fab fa-twitter"></i></a> <a href="#" class="btn text-contrast btn-circle btn-sm brand-youtube me-3"><i class="fab fa-youtube"></i></a> <a href="#" class="btn text-contrast btn-circle btn-sm brand-pinterest"><i class="fab fa-pinterest"></i></a></nav>
                    ?>
                </div>
            </footer>

        </main><!-- themeforest:js -->
        <script src="js/jquery.js"></script>
        <script src="js/bootstrap.bundle.js"></script>
        <script src="js/card.js"></script>
        <script src="js/counterup2.js"></script>
        <script src="js/noise.js"></script>
        <script src="js/noframework.waypoints.js"></script>
        <script src="js/odometer.js"></script>
        <script src="js/prism.js"></script>
        <script src="js/simplebar.js"></script>
        <script src="js/swiper-bundle.js"></script>
        <script src="js/jquery.easing.js"></script>
        <script src="js/jquery.validate.js"></script>
        <script src="js/jquery.smartWizard.js"></script>
        <script src="js/feather.js"></script>
        <script src="js/aos.js"></script>
        <script src="js/typed.js"></script>
        <script src="js/jquery.magnific-popup.js"></script>
        <script src="js/cookieconsent.js"></script>
        <script src="js/jquery.animatebar.js"></script>
        <script src="js/common.js"></script>
        <script src="js/forms.js"></script>
        <script src="js/stripe-bubbles.js"></script>
        <script src="js/stripe-menu.js"></script>
        <script src="js/credit-card.js"></script>
        <script src="js/pricing.js"></script>
        <script src="js/shop.js"></script>
        <script src="js/svg.js"></script>
        <script src="js/site.js"></script>
        <script src="js/wizards.js"></script>
        <script src="js/cookie-consent-util.js"></script>
        <script src="js/cookie-consent-themes.js"></script>
        <script src="js/cookie-consent-custom-css.js"></script>
        <script src="js/cookie-consent-informational.js"></script>
        <script src="js/cookie-consent-opt-out.js"></script>
        <script src="js/cookie-consent-opt-in.js"></script>
        <script src="js/cookie-consent-location.js"></script>
        <script src="js/demo.js"></script>
        <!-- endinject -->
    </body>

</html>