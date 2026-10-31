<?php require 'includes/auth.php'; page_head('Home'); ?>
<nav class="nav-top sticky-top py-3"><div class="container d-flex justify-content-between align-items-center">
  <a class="brand" href="index.php"><i class="bi bi-heart-pulse-fill"></i> MaternaDocs</a>
  <div class="d-flex gap-2">
    <?php if (current_user()): ?><a href="dashboard.php" class="btn btn-primary">Go to dashboard</a>
    <?php else: ?><a href="login.php" class="btn btn-primary">Sign in</a><?php endif; ?>
  </div></div></nav>

<header class="hero"><div class="container"><div class="row align-items-center g-5">
  <div class="col-lg-6">
    <span class="eyebrow mb-3"><i class="bi bi-hospital"></i> Bahay Paanakan · Lying-in Clinic</span>
    <h1 class="mt-3">Encode once. <em>Fill every form</em> without the errors.</h1>
    <p class="lead text-secondary my-4">MaternaDocs validates patient details before saving, auto-fills the clinic's documents, and lets patients view their own records without asking for a reprint.</p>
    <a href="login.php" class="btn btn-primary btn-lg me-2">Sign in</a>
    <a href="#how" class="btn btn-link text-decoration-none">See how it works</a>
  </div>
  <div class="col-lg-6"><div class="mock">
    <div class="d-flex justify-content-between mb-2"><strong>Patient profile</strong><span class="role-pill">Validated</span></div>
    <div class="row-i"><span>Birthdate</span><span class="ok"><i class="bi bi-check-circle-fill"></i> Not in the future</span></div>
    <div class="row-i"><span>PhilHealth no.</span><span class="ok"><i class="bi bi-check-circle-fill"></i> Format OK</span></div>
    <div class="row-i"><span>LMP → EDD</span><span class="ok"><i class="bi bi-check-circle-fill"></i> Dates consistent</span></div>
    <div class="row-i"><span>Duplicate check</span><span class="ok"><i class="bi bi-check-circle-fill"></i> No match found</span></div>
    <div class="row-i"><span>Forms ready</span><span class="text-secondary">Download as PDF</span></div>
  </div></div>
</div></div></header>

<section class="pb-5"><div class="container">
  <h2 class="text-center mb-4">One system, three kinds of access</h2>
  <div class="row g-4">
    <div class="col-md-4"><div class="card-soft"><div class="ico"><i class="bi bi-person-badge"></i></div><h5>Front Desk Staff</h5>
      <p class="text-secondary mb-0">Register patients, enter data with live validation, preview and download filled documents, and watch clinic activity on the dashboard.</p></div></div>
    <div class="col-md-4"><div class="card-soft"><div class="ico"><i class="bi bi-person-gear"></i></div><h5>Administrators</h5>
      <p class="text-secondary mb-0">Everything staff can do, plus managing user accounts and document templates, and reviewing the audit log.</p></div></div>
    <div class="col-md-4"><div class="card-soft"><div class="ico"><i class="bi bi-heart-pulse"></i></div><h5>Patients</h5>
      <p class="text-secondary mb-0">Securely view your own records and expected delivery date, and download your documents anytime. You only ever see your own data.</p></div></div>
  </div>
</div></section>

<section id="how" class="py-5 bg-white"><div class="container">
  <h2 class="text-center mb-5">How it works</h2>
  <div class="row g-4 text-center">
    <div class="col-md-4"><div class="step-n">1</div><h5>Enter data once</h5><p class="text-secondary">One profile per patient feeds every form.</p></div>
    <div class="col-md-4"><div class="step-n">2</div><h5>System validates</h5><p class="text-secondary">Required fields, date logic, ranges, ID formats, duplicates.</p></div>
    <div class="col-md-4"><div class="step-n">3</div><h5>Download the PDF</h5><p class="text-secondary">Review the preview, then download, ready for submission.</p></div>
  </div>
</div></section>

<footer class="py-4"><div class="container d-flex flex-wrap justify-content-between gap-2">
  <span data-secret-link="login.php?portal=staff">© <?= date('Y') ?> MaternaDocs</span></div></footer>
<?php page_foot(); ?>
