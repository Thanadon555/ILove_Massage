<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ข้อกำหนดและเงื่อนไข - ILove Massage</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.7;
        }

        .terms-header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 80px 0 60px;
            margin-bottom: 40px;
        }

        .terms-content {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 40px;
            margin-bottom: 40px;
        }

        .section-title {
            color: #2c3e50;
            border-left: 4px solid #ffc107;
            padding-left: 15px;
            margin: 30px 0 20px;
        }

        .highlight-box {
            background-color: #fff9e6;
            border-left: 4px solid #ffc107;
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 5px 5px 0;
        }

        .warning-box {
            background-color: #ffe6e6;
            border-left: 4px solid #dc3545;
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 5px 5px 0;
        }

        .info-box {
            background-color: #e8f4fd;
            border-left: 4px solid #0d6efd;
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 5px 5px 0;
        }

        .back-to-top {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #2c3e50;
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .back-to-top:hover {
            background: #ffc107;
            color: #2c3e50;
            transform: translateY(-3px);
        }

        .nav-pills .nav-link.active {
            background-color: #2c3e50;
        }

        .nav-pills .nav-link {
            color: #2c3e50;
            border-left: 3px solid transparent;
            border-radius: 0;
            padding: 10px 15px;
        }

        .nav-pills .nav-link:hover {
            background-color: #f8f9fa;
            border-left: 3px solid #ffc107;
        }

        .last-updated {
            background-color: #f8f9fa;
            padding: 10px 15px;
            border-radius: 5px;
            font-size: 0.9rem;
            margin-bottom: 20px;
        }

        .table-of-contents {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .terms-list {
            list-style-type: none;
            padding-left: 0;
        }

        .terms-list li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .terms-list li:last-child {
            border-bottom: none;
        }

        @media (max-width: 768px) {
            .terms-content {
                padding: 20px;
            }

            .terms-header {
                padding: 60px 0 40px;
            }
        }
    </style>
</head>

<body>
    <!-- Header -->
    <header class="terms-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-2 text-center">
                    <i class="fas fa-file-contract fa-4x text-warning"></i>
                </div>
                <div class="col-md-10">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php" class="text-white">หน้าแรก</a></li>
                            <li class="breadcrumb-item active text-warning" aria-current="page">ข้อกำหนดและเงื่อนไข</li>
                        </ol>
                    </nav>
                    <h1 class="display-5 fw-bold">ข้อกำหนดและเงื่อนไข</h1>
                    <p class="lead">ข้อตกลงการให้บริการของ ILove Massage</p>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="last-updated">
            <i class="fas fa-history me-2"></i>อัปเดตล่าสุด: 1 มกราคม 2024
        </div>

        <div class="row">
            <!-- Sidebar Navigation -->
            <div class="col-lg-3 mb-4">
                <div class="sticky-top" style="top: 20px;">
                    <div class="table-of-contents">
                        <h5 class="mb-3"><i class="fas fa-list me-2"></i>สารบัญ</h5>
                        <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist"
                            aria-orientation="vertical">
                            <a class="nav-link active" href="#section1">การยอมรับข้อกำหนด</a>
                            <a class="nav-link" href="#section2">การให้บริการ</a>
                            <a class="nav-link" href="#section3">การจองบริการ</a>
                            <a class="nav-link" href="#section4">การชำระเงิน</a>
                            <a class="nav-link" href="#section5">การยกเลิกและการคืนเงิน</a>
                            <a class="nav-link" href="#section6">ข้อจำกัดความรับผิดชอบ</a>
                            <a class="nav-link" href="#section7">การห้ามใช้บริการ</a>
                            <a class="nav-link" href="#section8">สิทธิ์ในทรัพย์สินทางปัญญา</a>
                            <a class="nav-link" href="#section9">การแก้ไขข้อกำหนด</a>
                            <a class="nav-link" href="#section10">กฎหมายที่ใช้บังคับ</a>
                            <a class="nav-link" href="#section11">ติดต่อเรา</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-9">
                <div class="terms-content">
                    <!-- Introduction -->
                    <div class="mb-5">
                        <p>ยินดีต้อนรับสู่ ILove Massage ข้อกำหนดและเงื่อนไขการให้บริการฉบับนี้อธิบายถึงกฎเกณฑ์ ข้อตกลง
                            และเงื่อนไขที่ใช้บังคับกับการใช้บริการของเรา</p>

                        <div class="highlight-box">
                            <h5><i class="fas fa-info-circle me-2"></i>โปรดทราบ</h5>
                            <p class="mb-0">การใช้งานเว็บไซต์และการจองบริการกับ ILove Massage หมายความว่าคุณได้อ่าน
                                ทำความเข้าใจ และยอมรับข้อกำหนดและเงื่อนไขทั้งหมดในหน้านี้แล้ว</p>
                        </div>
                    </div>

                    <!-- Section 1 -->
                    <section id="section1" class="mb-5">
                        <h3 class="section-title">1. การยอมรับข้อกำหนด</h3>
                        <p>โดยการเข้าถึงและใช้บริการของ ILove Massage (รวมถึงแต่ไม่จำกัดเพียงเว็บไซต์ แอปพลิเคชัน
                            และบริการนวด) คุณตกลงที่จะปฏิบัติตามและผูกพันกับข้อกำหนดและเงื่อนไขเหล่านี้</p>

                        <p>หากคุณไม่เห็นด้วยกับข้อกำหนดใดๆ ในข้อกำหนดและเงื่อนไขเหล่านี้ กรุณาอย่าใช้บริการของเรา</p>

                        <div class="info-box">
                            <h6><i class="fas fa-user-check me-2"></i>การยินยอม</h6>
                            <p class="mb-0">คุณยืนยันว่าคุณมีอายุอย่างน้อย 18
                                ปีบริบูรณ์และมีความสามารถตามกฎหมายในการทำข้อตกลงผูกพันเหล่านี้
                                หรือคุณได้รับอนุญาตจากผู้ปกครองหรือผู้พิทักษ์ให้ใช้บริการของเรา</p>
                        </div>
                    </section>

                    <!-- Section 2 -->
                    <section id="section2" class="mb-5">
                        <h3 class="section-title">2. การให้บริการ</h3>
                        <p>ILove Massage ให้บริการนวดประเภทต่างๆ ตามที่ระบุไว้ในเว็บไซต์ของเรา โดยเราขอสงวนสิทธิ์ในการ:
                        </p>

                        <ul class="terms-list">
                            <li><i class="fas fa-check text-success me-2"></i>แก้ไข ระงับ
                                หรือหยุดให้บริการบางส่วนหรือทั้งหมดไม่ว่าชั่วคราวหรือถาวร</li>
                            <li><i class="fas fa-check text-success me-2"></i>ปฏิเสธการให้บริการแก่ผู้ใช้ใดๆ
                                ด้วยเหตุผลใดๆ ก็ตาม ณ เวลาใดๆ</li>
                            <li><i class="fas fa-check text-success me-2"></i>เปลี่ยนแปลงอัตราค่าบริการได้ตามความเหมาะสม
                                โดยจะแจ้งให้ทราบล่วงหน้า</li>
                            <li><i
                                    class="fas fa-check text-success me-2"></i>ปรับเปลี่ยนประเภทและคุณภาพของบริการตามความเหมาะสม
                            </li>
                        </ul>

                        <h5>ข้อจำกัดทางการแพทย์</h5>
                        <p>คุณต้องแจ้งให้เราทราบเกี่ยวกับภาวะสุขภาพหรือข้อจำกัดทางการแพทย์ใดๆ ก่อนรับบริการ โดยเฉพาะ:
                        </p>

                        <div class="warning-box">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>ข้อควรระวัง</h6>
                            <ul class="mb-0">
                                <li>โรคหัวใจหรือความดันโลหิตสูง</li>
                                <li>การตั้งครรภ์</li>
                                <li>การบาดเจ็บหรือการผ่าตัดล่าสุด</li>
                                <li>โรคผิวหนังหรือการติดเชื้อ</li>
                                <li>ภาวะกระดูกหักหรือกระดูกพรุน</li>
                            </ul>
                        </div>
                    </section>

                    <!-- Section 3 -->
                    <section id="section3" class="mb-5">
                        <h3 class="section-title">3. การจองบริการ</h3>
                        <p>การจองบริการสามารถทำได้ผ่านช่องทางต่อไปนี้:</p>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="d-flex mb-3">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-globe text-warning fa-lg me-3"></i>
                                    </div>
                                    <div>
                                        <h6>เว็บไซต์</h6>
                                        <p class="mb-0">การจองผ่านเว็บไซต์ของเรา</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex mb-3">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-mobile-alt text-warning fa-lg me-3"></i>
                                    </div>
                                    <div>
                                        <h6>แอปพลิเคชัน</h6>
                                        <p class="mb-0">การจองผ่านแอปพลิเคชัน (หากมี)</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex mb-3">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-phone text-warning fa-lg me-3"></i>
                                    </div>
                                    <div>
                                        <h6>โทรศัพท์</h6>
                                        <p class="mb-0">การจองผ่านการโทรศัพท์</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex mb-3">
                                    <div class="flex-shrink-0">
                                        <i class="fab fa-line text-warning fa-lg me-3"></i>
                                    </div>
                                    <div>
                                        <h6>Line OA</h6>
                                        <p class="mb-0">การจองผ่าน Line Official Account</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <h5>การยืนยันการจอง</h5>
                        <p>การจองจะถือว่าสมบูรณ์เมื่อคุณได้รับอีเมลหรือข้อความยืนยันการจองจากเรา
                            กรุณาตรวจสอบรายละเอียดการจองให้ถูกต้อง</p>

                        <div class="highlight-box">
                            <h6><i class="fas fa-clock me-2"></i>เวลาการมาตรวจสอบ</h6>
                            <p class="mb-0">กรุณามาถึงก่อนเวลานัดหมายอย่างน้อย 15 นาที
                                เพื่อเตรียมตัวและกรอกข้อมูลที่จำเป็น หากมาสายเกิน 15 นาที
                                เราขอสงวนสิทธิ์ในการยกเลิกการจองของคุณ</p>
                        </div>
                    </section>

                    <!-- Section 4 -->
                    <section id="section4" class="mb-5">
                        <h3 class="section-title">4. การชำระเงิน</h3>
                        <p>เรารับชำระเงินผ่านช่องทางต่อไปนี้:</p>

                        <div class="row mb-4">
                            <div class="col-md-4 text-center mb-3">
                                <div class="border rounded p-3">
                                    <i class="fas fa-money-bill-wave fa-2x text-success mb-2"></i>
                                    <h6>เงินสด</h6>
                                </div>
                            </div>
                            <div class="col-md-4 text-center mb-3">
                                <div class="border rounded p-3">
                                    <i class="fas fa-credit-card fa-2x text-primary mb-2"></i>
                                    <h6>บัตรเครดิต/เดบิต</h6>
                                </div>
                            </div>
                            <div class="col-md-4 text-center mb-3">
                                <div class="border rounded p-3">
                                    <i class="fas fa-mobile fa-2x text-info mb-2"></i>
                                    <h6>พร้อมเพย์</h6>
                                </div>
                            </div>
                        </div>

                        <h5>อัตราค่าบริการ</h5>
                        <p>อัตราค่าบริการจะแสดงบนเว็บไซต์และแอปพลิเคชันของเรา และอาจมีการเปลี่ยนแปลงได้ตามความเหมาะสม
                            เราจะแจ้งให้ทราบล่วงหน้าถ้ามีการเปลี่ยนแปลงอัตราค่าบริการ</p>

                        <div class="info-box">
                            <h6><i class="fas fa-receipt me-2"></i>ใบเสร็จรับเงิน</h6>
                            <p class="mb-0">เราจะออกใบเสร็จรับเงิน/ใบกำกับภาษีให้ทุกครั้งหลังจากชำระเงินเสร็จสิ้น
                                คุณสามารถขอรับได้ที่หน้าร้านหรือทางอีเมล</p>
                        </div>
                    </section>

                    <!-- Section 5 -->
                    <section id="section5" class="mb-5">
                        <h3 class="section-title">5. การยกเลิกและการคืนเงิน</h3>

                        <h5>5.1 การยกเลิกโดยลูกค้า</h5>
                        <p>คุณสามารถยกเลิกการจองได้โดยแจ้งล่วงหน้าดังนี้:</p>

                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>ระยะเวลาการแจ้งยกเลิก</th>
                                        <th>ค่าธรรมเนียม</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>มากกว่า 24 ชั่วโมง</td>
                                        <td>ไม่คิดค่าธรรมเนียม</td>
                                    </tr>
                                    <tr>
                                        <td>4 - 24 ชั่วโมง</td>
                                        <td>50% ของค่าบริการ</td>
                                    </tr>
                                    <tr>
                                        <td>น้อยกว่า 4 ชั่วโมง</td>
                                        <td>100% ของค่าบริการ</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <h5>5.2 การยกเลิกโดยร้าน</h5>
                        <p>เราขอสงวนสิทธิ์ในการยกเลิกการจองของคุณในกรณีต่อไปนี้:</p>

                        <ul class="terms-list">
                            <li><i
                                    class="fas fa-times text-danger me-2"></i>หมอนวดไม่สามารถให้บริการได้เนื่องจากเหตุสุดวิสัย
                            </li>
                            <li><i class="fas fa-times text-danger me-2"></i>สถานที่ให้บริการไม่พร้อมให้บริการ</li>
                            <li><i class="fas fa-times text-danger me-2"></i>ลูกค้ามีพฤติกรรมที่ไม่เหมาะสม</li>
                            <li><i
                                    class="fas fa-times text-danger me-2"></i>ลูกค้ามีอาการเจ็บป่วยที่อาจเป็นอันตรายจากการรับบริการนวด
                            </li>
                        </ul>

                        <p>ในกรณีที่เราต้องยกเลิกการจอง เราจะแจ้งให้คุณทราบล่วงหน้าและคืนเงินเต็มจำนวน
                            (หากชำระเงินล่วงหน้าแล้ว)</p>

                        <h5>5.3 การคืนเงิน</h5>
                        <p>การคืนเงินจะดำเนินการภายใน 7-14 วันทำการ ผ่านช่องทางเดิมที่ใช้ชำระเงิน</p>
                    </section>

                    <!-- Section 6 -->
                    <section id="section6" class="mb-5">
                        <h3 class="section-title">6. ข้อจำกัดความรับผิดชอบ</h3>
                        <p>ILove Massage จะไม่รับผิดชอบต่อความเสียหายใดๆ ที่เกิดขึ้นจากการใช้บริการของเรา
                            ยกเว้นในกรณีที่เกิดจากความประมาทเลินเล่ออย่างร้ายแรงของเรา</p>

                        <h5>ข้อจำกัดความรับผิดชอบเฉพาะ</h5>
                        <ul class="terms-list">
                            <li><i class="fas fa-ban text-danger me-2"></i>เราไม่รับผิดชอบต่อความเสียหายทางอ้อม
                                ความเสียหายที่เป็นผลสืบเนื่อง หรือความเสียหายจากการสูญเสียโอกาส</li>
                            <li><i
                                    class="fas fa-ban text-danger me-2"></i>เราไม่รับผิดชอบต่อทรัพย์สินสูญหายหรือเสียหายในระหว่างการให้บริการ
                            </li>
                            <li><i
                                    class="fas fa-ban text-danger me-2"></i>เราไม่รับผิดชอบต่อการบาดเจ็บที่เกิดจากการไม่เปิดเผยข้อมูลทางการแพทย์ที่สำคัญ
                            </li>
                            <li><i
                                    class="fas fa-ban text-danger me-2"></i>เราไม่รับผิดชอบต่อความล่าช้าที่เกิดจากเหตุสุดวิสัย
                            </li>
                        </ul>

                        <div class="warning-box">
                            <h6><i class="fas fa-exclamation-circle me-2"></i>ความรับผิดชอบสูงสุด</h6>
                            <p class="mb-0">ความรับผิดชอบทั้งหมดของ ILove Massage ต่อคุณไม่ว่าจะในทางสัญญาหรือทางละเมิด
                                จะถูกจำกัดไว้ที่จำนวนเงินที่คุณได้ชำระสำหรับบริการที่เกี่ยวข้องเท่านั้น</p>
                        </div>
                    </section>

                    <!-- Section 7 -->
                    <section id="section7" class="mb-5">
                        <h3 class="section-title">7. การห้ามใช้บริการ</h3>
                        <p>เราขอสงวนสิทธิ์ในการปฏิเสธหรือระงับการให้บริการแก่ผู้ใช้ใดๆ ที่:</p>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="d-flex mb-3">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-user-slash text-danger me-3"></i>
                                    </div>
                                    <div>
                                        <h6>พฤติกรรมไม่เหมาะสม</h6>
                                        <p class="mb-0">มีพฤติกรรมที่ไม่เหมาะสมต่อพนักงานหรือลูกค้าท่านอื่น</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex mb-3">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-wine-bottle text-danger me-3"></i>
                                    </div>
                                    <div>
                                        <h6>มึนเมา</h6>
                                        <p class="mb-0">อยู่ในสภาพมึนเมาจากแอลกอฮอล์หรือสารเสพติด</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex mb-3">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-ban text-danger me-3"></i>
                                    </div>
                                    <div>
                                        <h6>ละเมิดข้อกำหนด</h6>
                                        <p class="mb-0">ละเมิดข้อกำหนดและเงื่อนไขการให้บริการ</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex mb-3">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-shield-alt text-danger me-3"></i>
                                    </div>
                                    <div>
                                        <h6>ความปลอดภัย</h6>
                                        <p class="mb-0">สร้างความเสี่ยงต่อความปลอดภัยของพนักงานหรือลูกค้า</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Section 8 -->
                    <section id="section8" class="mb-5">
                        <h3 class="section-title">8. สิทธิ์ในทรัพย์สินทางปัญญา</h3>
                        <p>เนื้อหาทั้งหมดบนเว็บไซต์และแอปพลิเคชันของ ILove Massage รวมถึงแต่ไม่จำกัดเพียง ข้อความ กราฟิก
                            โลโก้ ไอคอน รูปภาพ คลิปวิดีโอ และซอฟต์แวร์ เป็นทรัพย์สินของ ILove Massage
                            หรือผู้ให้อนุญาตและได้รับการคุ้มครองโดยกฎหมายลิขสิทธิ์และกฎหมายทรัพย์สินทางปัญญาอื่นๆ</p>

                        <div class="highlight-box">
                            <h6><i class="fas fa-copyright me-2"></i>การห้ามนำไปใช้</h6>
                            <p class="mb-0">ห้ามคัดลอก ทำซ้ำ แจกจ่าย แสดง หรือใช้เนื้อหาใดๆ
                                จากเว็บไซต์ของเราเพื่อวัตถุประสงค์ทางการค้าโดยไม่ได้รับอนุญาตเป็นลายลักษณ์อักษรจากเรา
                            </p>
                        </div>
                    </section>

                    <!-- Section 9 -->
                    <section id="section9" class="mb-5">
                        <h3 class="section-title">9. การแก้ไขข้อกำหนด</h3>
                        <p>เราขอสงวนสิทธิ์ในการแก้ไข เปลี่ยนแปลง หรือปรับปรุงข้อกำหนดและเงื่อนไขเหล่านี้ได้ตลอดเวลา
                            โดยจะแจ้งให้ทราบผ่านทาง:</p>

                        <ul>
                            <li>การโพสต์ข้อกำหนดและเงื่อนไขฉบับแก้ไขบนเว็บไซต์ของเรา</li>
                            <li>การส่งอีเมลแจ้งเตือนไปยังที่อยู่อีเมลที่คุณให้ไว้</li>
                            <li>การแจ้งเตือนในแอปพลิเคชัน (หากใช้)</li>
                        </ul>

                        <p>การเปลี่ยนแปลงจะมีผลบังคับใช้ทันทีหลังจากที่เราโพสต์ข้อกำหนดและเงื่อนไขฉบับแก้ไข
                            การใช้บริการของเราหลังจากที่มีการเปลี่ยนแปลงหมายความว่าคุณยอมรับข้อกำหนดและเงื่อนไขฉบับแก้ไขแล้ว
                        </p>

                        <div class="info-box">
                            <h6><i class="fas fa-sync-alt me-2"></i>การตรวจสอบเป็นประจำ</h6>
                            <p class="mb-0">เราขอแนะนำให้คุณตรวจสอบหน้านี้เป็นระยะเพื่อรับทราบการเปลี่ยนแปลงใดๆ
                                เราได้ระบุวันที่อัปเดตล่าสุดไว้ด้านบนของหน้านี้</p>
                        </div>
                    </section>

                    <!-- Section 10 -->
                    <section id="section10" class="mb-5">
                        <h3 class="section-title">10. กฎหมายที่ใช้บังคับ</h3>
                        <p>ข้อกำหนดและเงื่อนไขเหล่านี้จะถูกตีความและควบคุมโดยกฎหมายของประเทศไทย</p>

                        <p>ข้อพิพาทใดๆ
                            ที่เกิดขึ้นจากหรือเกี่ยวข้องกับข้อกำหนดและเงื่อนไขเหล่านี้หรือการใช้บริการของเราจะอยู่ภายใต้เขตอำนาจศาลของศาลในประเทศไทยแต่เพียงผู้เดียว
                        </p>
                    </section>

                    <!-- Section 11 -->
                    <section id="section11" class="mb-5">
                        <h3 class="section-title">11. ติดต่อเรา</h3>
                        <p>หากคุณมีคำถาม ข้อกังวล หรือความคิดเห็นเกี่ยวกับข้อกำหนดและเงื่อนไขเหล่านี้
                            กรุณาติดต่อเราได้ที่:</p>

                        <div class="info-box">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6><i class="fas fa-building me-2"></i>I Love Massage</h6>
                                    <p class="mb-2">ช้างเผือก 30 ซอย สุขเกษม 1</p>
                                    <p class="mb-2">ตำบลช้างเผือก เมือง เชียงใหม่ 50200</p>
                                </div>
                                <div class="col-md-6">
                                    <h6><i class="fas fa-address-card me-2"></i>ข้อมูลติดต่อ</h6>
                                    <p class="mb-2"><i class="fas fa-phone me-2"></i> 082-6843254</p>
                                    <p class="mb-2"><i class="fas fa-envelope me-2"></i> terms@ilovemassage.com</p>
                                    <p class="mb-0"><i class="fas fa-clock me-2"></i> จันทร์-ศุกร์ 09:00-18:00 น.</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Final Note -->
                    <div class="text-center mt-5 pt-4 border-top">
                        <p class="text-muted">ขอบคุณที่เลือกใช้บริการ ILove Massage</p>
                        <a href="../index.php" class="btn btn-warning me-2">
                            <i class="fas fa-home me-2"></i>กลับสู่หน้าหลัก
                        </a>
                        <a href="privacy.php" class="btn btn-outline-secondary">
                            <i class="fas fa-user-shield me-2"></i>นโยบายความเป็นส่วนตัว
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Back to Top Button -->
    <a href="#" class="back-to-top">
        <i class="fas fa-chevron-up"></i>
    </a>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">&copy; 2024 ILove Massage. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="terms.php" class="text-white text-decoration-none me-3">ข้อกำหนดและเงื่อนไข</a>
                    <a href="privacy.php" class="text-white text-decoration-none">นโยบายความเป็นส่วนตัว</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();

                const targetId = this.getAttribute('href');
                if (targetId === '#') return;

                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 100,
                        behavior: 'smooth'
                    });

                    // Update active nav link
                    document.querySelectorAll('.nav-pills .nav-link').forEach(link => {
                        link.classList.remove('active');
                    });
                    this.classList.add('active');
                }
            });
        });

        // Update active nav link on scroll
        window.addEventListener('scroll', function () {
            const sections = document.querySelectorAll('section');
            const navLinks = document.querySelectorAll('.nav-pills .nav-link');

            let currentSection = '';

            sections.forEach(section => {
                const sectionTop = section.offsetTop - 150;
                if (window.scrollY >= sectionTop) {
                    currentSection = section.getAttribute('id');
                }
            });

            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === '#' + currentSection) {
                    link.classList.add('active');
                }
            });
        });

        // Back to top button
        const backToTopButton = document.querySelector('.back-to-top');

        window.addEventListener('scroll', function () {
            if (window.pageYOffset > 300) {
                backToTopButton.style.display = 'flex';
            } else {
                backToTopButton.style.display = 'none';
            }
        });

        backToTopButton.addEventListener('click', function (e) {
            e.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    </script>
</body>

</html>