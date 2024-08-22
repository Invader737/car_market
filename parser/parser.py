import sys
import json
import warnings
import os
import logging
import os
import requests
from playwright.sync_api import sync_playwright # type: ignore

warnings.filterwarnings("ignore")

def download_image(session, url, file_path):
    try:
        # Ensure the directory exists
        os.makedirs(os.path.dirname(file_path), exist_ok=True)
        
        # Download the image
        response = session.get(url)
        logging.info(f"Статус: {response}")
        if response.status_code == 200:
            with open(file_path, 'wb') as f:
                f.write(response.content)
                logging.info(f"Изображение сохранено: {file_path}")
        else:
            logging.error(f"Не удалось загрузить изображение: {url}, статус: {response.status_code}")
    except Exception as e:
        logging.error(f"Ошибка при загрузке изображения: {e}")

def parse_photos(page, upload_id, car_id, logs):
    image_paths = []
    try:
        next_button = page.locator(".SliderControls__StyledButton-sc-1dbsnpt-4.cIKvvT").first
        
        if next_button is None:
            logging.error("Кнопка 'Далее' не найдена.")
            return {"error": "Кнопка 'Далее' не найдена.", "car_id": car_id, "upload_id": upload_id, "url": page.url}, image_paths
        
        parent_element = next_button.evaluate_handle("el => el.parentElement")
        blocks_with_images_handle = parent_element.evaluate_handle("el => el.firstElementChild.firstElementChild.children")
        blocks_with_images = blocks_with_images_handle.evaluate("el => Array.from(el)")
        
        if not blocks_with_images:
            logging.error("Блоки с изображениями не найдены.")
            return {"error": "Блоки с изображениями не найдены.", "car_id": car_id, "upload_id": upload_id, "url": page.url}, image_paths
        
        num_blocks = len(blocks_with_images)
        
        for i in range(num_blocks):
            next_button.click()
            page.wait_for_timeout(500)
        
        image_urls = []
        for idx in range(num_blocks):
            block_handle = blocks_with_images_handle.evaluate_handle(f"el => el[{idx}]")
            background_image = block_handle.evaluate("el => el.style.backgroundImage")
            if background_image:
                image_url = background_image.split('"')[1]
                image_urls.append(image_url)
        
        for idx, image_url in enumerate(image_urls):
            clean_url = image_url.split('?')[0]
            ext = clean_url.split('.')[-1]
            file_name = f"{car_id}_{idx+1:02d}.{ext}"
            
            # Построение пути, начиная с корневой директории
            root_dir = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
            file_path = os.path.join(root_dir, "uploads", upload_id, "images", file_name)
            
            # Создание директорий, если они не существуют
            os.makedirs(os.path.dirname(file_path), exist_ok=True)
            
            image_paths.append(file_path)
            
            with requests.Session() as session:
                download_image(session, image_url, file_path)
                
        logs.append("Все изображения успешно сохранены.")
        logging.info("Все изображения успешно сохранены.")
    
    except Exception as e:
        logging.error(f"Ошибка при парсинге фотографий: {str(e)}")
        return {"error": str(e), "car_id": car_id, "upload_id": upload_id, "url": page.url}, image_paths

    return None, image_paths

def parse_page(url, upload_id, car_id):
    logs = []
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        
        try:
            logging.info(f"Переход на страницу: {url}")
            page.goto(url)
            page.wait_for_timeout(2000)

            if check_iframe_visible(page, logs):
                switch_to_iframe(page, logs)
                accept_cookies(page, logs)
                switch_back_to_main_content(page, logs)
            else:
                logging.info("Iframe не найден или не виден, пропуск шагов для куки.")
            
            if page.locator("h1:has-text('Annonsen finns inte längre')").count() > 0:
                logging.info("Объявление снято с публикации.")
                return {
                    "car_id": car_id,
                    "upload_id": upload_id,
                    "url": url,
                    "status": "Объявление снято с публикации",
                    "cubicCapacity": None,
                    "driveType": None,
                    "bodyType": None,
                    "horsepower": None,
                    "CO2-Emission": None,
                    "euroNorm": None,
                    "fuelConsumption": None,
                    "numberOfSeats": None,
                    "image_paths": []
                }
                
            error, image_paths = parse_photos(page, upload_id, car_id, logs)
            if error:
                return error

            click_show_more(page, logs)

            cubic_capacity = get_value(page, logs, "Motorstorlek")
            drive_type = get_value(page, logs, "Drivning")
            body_type = get_value(page, logs, "Biltyp")
            
            click_accordion_button(page, "Motor och miljö", logs)
            horsepower = get_span_value(page, logs, "Motoreffekt")
            co2_emissions = get_span_value(page, logs, "-utsläpp")
            euro_norm = get_span_value(page, logs, "Utsläppsklass")
            fuel_consumption = get_fuel_consumption(page, logs)
            
            click_accordion_button(page, "Basfakta", logs)
            number_of_seats = get_span_value(page, logs, "Antal säten")

            result = {
                "car_id": car_id,
                "upload_id": upload_id,
                "url": url,
                "status": "Объявление доступно",
                "cubicCapacity": cubic_capacity,
                "driveType": drive_type,
                "bodyType": body_type,
                "horsepower": horsepower,
                "CO2-Emission": co2_emissions,
                "euroNorm": euro_norm,
                "fuelConsumption": fuel_consumption,
                "numberOfSeats": number_of_seats,
                "image_paths": image_paths
            }
            return result

        except Exception as e:
            logging.error(f"Ошибка при парсинге страницы {url}: {str(e)}")
            return {"error": str(e), "logs": logs}
        
        finally:
            browser.close()

def check_iframe_visible(page, logs):
    try:
        iframe = page.frame("sp_message_iframe_1147452")
        if iframe and iframe.is_visible("body"): 
            logs.append("Iframe is visible.")
            logging.info("Iframe найден и видим.")
            return True
        else:
            logs.append("Iframe is not visible.")
            logging.info("Iframe не найден или не видим.")
            return False
    except Exception as e:
        logs.append(f"Error checking iframe visibility: {str(e)}")
        logging.error(f"Ошибка при проверке видимости iframe: {str(e)}")
        return False

def switch_to_iframe(page, logs):
    try:
        iframe = page.frame("sp_message_iframe_1147452")
        if iframe:
            logs.append("Switched to iframe")
            logging.info("Переключено на iframe с ID 'sp_message_iframe_1147452'")
        else:
            raise Exception("Iframe not found")
    except Exception as e:
        logs.append(f"Error switching to iframe: {str(e)}")
        logging.error(f"Ошибка при переключении на iframe: {str(e)}")

def accept_cookies(page, logs):
    try:
        iframe = page.frame("sp_message_iframe_1147452")
        iframe.wait_for_selector("button[title='Godkänn alla cookies']", timeout=2000)
        
        cookie_button = iframe.locator("button[title='Godkänn alla cookies']")
        cookie_button.click()
        
        logs.append("Cookie button clicked successfully")
        logging.info("Кнопка согласия с куки нажата успешно")
    except Exception as e:
        logs.append(f"Error clicking cookie button: {str(e)}")
        logging.error(f"Ошибка при нажатии кнопки куки: {str(e)}")

def switch_back_to_main_content(page, logs):
    try:
        logs.append("Switched back to default content")
        logging.info("Переключено обратно на основной контент")
    except Exception as e:
        logs.append(f"Error switching back to default content: {str(e)}")
        logging.error(f"Ошибка при переключении обратно на основной контент: {str(e)}")

def click_show_more(page, logs):
    try:
        show_more_buttons = page.locator(".ExpandableContent__StyledShowMoreButton-sc-11a0rym-2")
        if show_more_buttons.count() == 0:
            logs.append("Show more button not found.")
            logging.info("Кнопка 'Показать больше' не найдена.")
            return

        show_more_button = show_more_buttons.nth(0)
        show_more_button.wait_for(state='visible', timeout=2000)
        show_more_button.click()
        
        logs.append("Show more button clicked successfully")
        logging.info("Кнопка 'Показать больше' нажата успешно")
        
        page.wait_for_timeout(2000) 
    except Exception as e:
        logs.append(f"Error clicking 'Show more' button: {str(e)}")
        logging.error(f"Ошибка при нажатии кнопки 'Показать больше': {str(e)}")

def get_value(page, logs, label_text):
    try:
        label = page.locator(f"//div[contains(text(), '{label_text}')]").first
        parent_div = label.locator("..")
        value = parent_div.locator("div").nth(1).text_content()
        logs.append(f"{label_text} value retrieved successfully: {value}")
        logging.info(f"Значение '{label_text}' успешно получено: {value}")
        return value
    except Exception as e:
        logs.append(f"Error retrieving {label_text} value: {str(e)}")
        logging.error(f"Ошибка при получении значения '{label_text}': {str(e)}")
        return "Value not found"
    
def get_span_value(page, logs, label_text):
    try:
        label = page.locator(f"//span[contains(text(), '{label_text}')]").first
        parent_span = label.locator("..")
        value = parent_span.locator("span").nth(1).text_content()
        logs.append(f"{label_text} value retrieved successfully: {value}")
        logging.info(f"Значение '{label_text}' успешно получено: {value}")
        return value
    except Exception as e:
        logs.append(f"Error retrieving {label_text} value: {str(e)}")
        logging.error(f"Ошибка при получении значения '{label_text}': {str(e)}")
        return "Value not found"

def get_fuel_consumption(page, logs):
    try:
        elements = page.locator("//span[contains(text(), 'Bränsleförbrukning')]").all()
        values = []
        for element in elements:
            parent = element.locator("..")
            spans = parent.locator("span").all()
            label = spans[1].text_content().strip("()")
            value = spans[2].text_content()
            values.append(f"{label}: {value}")
        result = ", ".join(values)
        logs.append("Fuel consumption values retrieved successfully: " + result)
        logging.info("Значения расхода топлива успешно получены: " + result)
        return result
    except Exception as e:
        logs.append(f"Error retrieving fuel consumption values: {str(e)}")
        logging.error(f"Ошибка при получении значений расхода топлива: {str(e)}")
        return "Values not found"

def click_accordion_button(page, text, logs):
    try:
        accordion_button = page.locator(f"//div[contains(@class, 'Transportstyrelsen__AccordionTitle') and .//span[text()='{text}']]").first
        accordion_button.click()
        logs.append(f"Accordion button with text '{text}' clicked successfully")
        logging.info(f"Кнопка аккордеона с текстом '{text}' нажата успешно")
    except Exception as e:
        logs.append(f"Error clicking accordion button with text '{text}': {str(e)}")
        logging.error(f"Ошибка при нажатии кнопки аккордеона с текстом '{text}': {str(e)}")

def main():
    logging.basicConfig(
        level=logging.DEBUG, 
        format='%(asctime)s - %(levelname)s - %(message)s', 
    )
    data = json.loads(sys.argv[1]) 
    
    if not isinstance(data, dict):
        raise ValueError("Expected data to be a dictionary")

    upload_id = data.get("upload_id")
    urls = data.get("urls")

    if upload_id is None or urls is None:
        raise ValueError("Missing upload_id or urls in input data")

    results = []
    for car_id, url in urls.items(): 
        result = parse_page(url, upload_id, car_id) 
        results.append(result)

    print(json.dumps(results, indent=2))

if __name__ == "__main__":
    main()
