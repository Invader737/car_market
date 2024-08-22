jQuery(function ($) {
    $(document).ready(function () {

        function getFolderIdFromUrl() {
            const params = new URLSearchParams(window.location.search);
            return params.get('folderId');
        }

        function loadJsonData(folderId) {
            $.ajax({
                url: `/app/edit/index.php?folderId=${folderId}`,
                type: 'GET',
                success: function (response) {
                    try {
                        const jsonData = JSON.parse(response);
                        renderJsonEditor(jsonData);
                    } catch (e) {
                        alert('Error parsing server response.');
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    alert('Error fetching data:', textStatus, errorThrown);
                }
            });
        }

        const attributeMapping = {
            "_id": "car_id",
            "CO2-Emission": "CO2-Emission",
            "averageCO2Emissions": "averageCO2Emissions",
            "bodyType": "bodyType",
            "brand": "Brand",
            "brandModel": "Model",
            "carNumber": "carNumber",
            "cubicCapacity": "cubicCapacity",
            "cylinders": "cylinders",
            "discount": "discount",
            "doors": "doors",
            "driveType": "driveType",
            "electricityConsumption": "electricityConsumption",
            "emptyWeight": "emptyWeight",
            "energyEfficiency": "energyEfficiency",
            "euroNorm": "euroNorm",
            "exteriorColor": "Colour",
            "fuel": "Fuel type",
            "fuelConsumption": "fuelConsumption",
            "guarantee": "guarantee",
            "horsepower": "horsepower",
            "images": "images",
            "interiorColor": "interiorColor",
            "lastInspection": "lastInspection",
            "mileage": "milage",
            "optionalEquipment": "optionalEquipment",
            "placingOnTheMarket": "placingOnTheMarket",
            "price": "Purchase price €",
            "seats": "numberOfSeats",
            "standardEquipment": "standardEquipment",
            "title": "title",
            "trailerLoad": "trailerLoad",
            "transmissionType": "transmissionType",
            "vehicleCondition": "vehicleCondition",
            "availableFor": "availableFor",
            "image_paths": "image_paths" // added for image paths
        };

        function renderJsonEditor(data) {
            const editorContainer = $('#json-editor');
            editorContainer.empty(); // Clear any existing content

            data.forEach((car, index) => {
                const carContainer = $('<div>').addClass('car-container mb-4');
                carContainer.append(`<h4>Car ${index + 1}</h4>`);

                Object.keys(attributeMapping).forEach(key => {
                    const originalKey = attributeMapping[key];
                    let value = car[originalKey] || '';

                    if (Array.isArray(value) && key === 'image_paths') {
                        const imagesContainer = $('<div class="mb-3 row"></div>');
                        imagesContainer.append(`<label class="col-sm-2 col-form-label">${key}</label>`);

                        const imagesContent = $('<div class="col-sm-10"></div>');
                        value.forEach((path, i) => {
                            const relativePath = path.replace('/var/www/www-root/data/www/194-58-121-90.cloudvps.regruhosting.ru', '').replace('/images/', '/optimaized/');

                            const imageGroup = `
                                <div class="mb-2 d-flex align-items-center">
                                    <img src="${relativePath}" alt="Image ${i + 1}" id="image-preview-${index}-${i}" class="img-thumbnail me-2" style="max-width: 200px;">
                                    <input hidden type="text" class="form-control" id="car-${index}-key-${key}-${i}" value="${relativePath}">
                                    <button type="button" class="btn btn-warning ms-2 reset-image" data-index="${index}" data-key="${key}" data-i="${i}">Reset</button>
                                </div>
                            `;
                            imagesContent.append(imageGroup);
                        });
                        imagesContainer.append(imagesContent);
                        carContainer.append(imagesContainer);
                    } else if (Array.isArray(value)) {
                        const listContainer = `
                            <div class="mb-3 row">
                                <label class="col-sm-2 col-form-label">${key}</label>
                                <div class="col-sm-10">
                                    ${value.map((item, i) => `
                                        <input type="text" class="form-control mb-2" id="car-${index}-key-${key}-${i}" value="${item}">
                                    `).join('')}
                                </div>
                            </div>
                        `;
                        carContainer.append(listContainer);
                    } else {
                        const inputGroup = `
                            <div class="mb-3 row">
                                <label for="car-${index}-key-${key}" class="col-sm-2 col-form-label">${key}</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control json-value" id="car-${index}-key-${key}" value="${value}">
                                </div>
                            </div>
                        `;
                        carContainer.append(inputGroup);
                    }
                });

                editorContainer.append(carContainer);
            });

            const saveButton = '<button type="button" class="btn btn-success" id="save-changes">Save Changes</button>';
            editorContainer.append(saveButton);

            $('.reset-image').on('click', function () {
                const index = $(this).data('index');
                const key = $(this).data('key');
                const i = $(this).data('i');
                const originalPath = data[index][attributeMapping[key]][i].replace('/var/www/www-root/data/www/194-58-121-90.cloudvps.regruhosting.ru', '');
                $(`#car-${index}-key-${key}-${i}`).val(originalPath);
                $(`#image-preview-${index}-${i}`).attr('src', originalPath);
                console.log('ori', originalPath)
            });

            $('#save-changes').on('click', function () {
                saveJsonData(data);
            });
        }

        function saveJsonData(originalData) {
            const updatedData = originalData.map((car, index) => {
                const updatedCar = {};

                Object.keys(attributeMapping).forEach(key => {
                    const originalKey = attributeMapping[key];

                    if (Array.isArray(car[originalKey]) && key === 'image_paths') {
                        updatedCar[key] = car[originalKey].map((_, i) => $(`#car-${index}-key-${key}-${i}`).val());
                    } else if (Array.isArray(car[originalKey])) {
                        updatedCar[key] = car[originalKey].map((_, i) => $(`#car-${index}-key-${key}-${i}`).val());
                    } else {
                        updatedCar[key] = $(`#car-${index}-key-${key}`).val();
                    }
                });

                return updatedCar;
            });

            $.ajax({
                url: `/app/edit/index.php?folderId=${getFolderIdFromUrl()}`,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(updatedData),
                success: function (response) {
                    const blob = new Blob([JSON.stringify(updatedData, null, 2)], { type: 'application/json' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'updated_data.json';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    alert('Data saved successfully and downloaded.');
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    alert('Error saving data:', textStatus, errorThrown);
                }
            });
        }

        // Initial load
        const folderId = getFolderIdFromUrl();
        if (folderId) {
            loadJsonData(folderId);
        } else {
            console.log('No folderId found in the URL.');
        }

        $('[data-button="upload"]').on('click', function (e) {
            e.preventDefault();

            var fileInput = $('.form-file')[0];
            var file = fileInput.files[0];

            if (file) {
                var formData = new FormData();
                formData.append('file', file);
                $.ajax({
                    url: 'app/upload/index.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    beforeSend: function () {
                        $('#uploadBtn').addClass('loading');
                        $('.progress-bar').css('width', '0%');
                        $('.progress-bar').attr('aria-valuenow', 0);
                    },
                    xhr: function () {
                        var xhr = new window.XMLHttpRequest();
                        xhr.upload.addEventListener('progress', function (evt) {
                            if (evt.lengthComputable) {
                                var percentComplete = evt.loaded / evt.total * 100;
                                $('.progress').show();
                                $('.progress-bar').css('width', percentComplete + '%');
                                $('.progress-bar').attr('aria-valuenow', percentComplete);
                            }
                        }, false);
                        return xhr;
                    },
                    success: function (response) {
                        $('#uploadBtn').removeClass('loading');
                        try {
                            response = JSON.parse(response);
                        } catch (e) {
                            alert('Error parsing server response.');
                            return;
                        }

                        loadJsonData(response.upload_id);

                        if (response.error) {
                            alert(response.error);
                        } else {
                            alert(response.success);
                            const downloadLink = document.createElement('a');
                            downloadLink.href = response.path;
                            downloadLink.download = 'output.json';
                            document.body.appendChild(downloadLink);
                            //downloadLink.click();
                            document.body.removeChild(downloadLink);
                        }

                        $('.progress-bar').removeClass('bg-danger');
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        $('#uploadBtn').removeClass('loading');
                        alert('Error uploading file:', textStatus, errorThrown);
                        $('.progress-bar').addClass('bg-danger');
                    }
                });
            } else {
                alert("No file selected.");
            }
        })

    });
});