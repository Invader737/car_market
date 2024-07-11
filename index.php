<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import & Export API - Cars Marketplace</title>
    <link rel="stylesheet" href="/assets/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/main.css">
</head>

<body>

    <main class="container">
        <div class="row">
            <div class="col-12">
                <div class="px-4 py-5 my-5 text-center">
                    <h1 class="display-5 fw-bold text-body-emphasis">Import & Export API</h1>
                    <div class="col-lg-8 mx-auto">
                        <p class="lead mb-4">Here you can receive your goods in the desired form quickly and conveniently.</p>
                        <div class="row">
                            <div class="col-lg">
                                <input class="form-control  form-file" type="file" id="formFile">
                            </div>
                            <div class="col-lg-auto">
                                <button type="button" class="btn btn-primary px-5" data-button="upload">Upload</button>
                            </div>
                        </div>
                        <div class="progress mt-3" style="display: none;">
                            <div class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>


    <script src="/assets/jquery-3.7.1.min.js"></script>
    <script src="/assets/main.js"></script>
</body>

</html>