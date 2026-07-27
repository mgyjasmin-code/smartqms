

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&family=Inter:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= assetUrl('assets/css/style.css') ?>">
</head>
<body>
    <h2>SMS API Integration in PHP </h2>
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">SMS API Integration</h3>
                    </div>
                    <div class="card-body">
                        <form action="" method="post">
                            <div class="form-group">
                                <label for="mobile_number">Mobile Number</label>
                                <input type="text" name="mobile_number" id="mobile_number" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="message">Message</label>
                                <textarea name="message" id="message" class="form-control"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Send SMS</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= assetUrl('index.js') ?>"></script>
</body>
</html>