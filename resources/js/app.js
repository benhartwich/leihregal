import './bootstrap';
import { BrowserMultiFormatReader } from '@zxing/browser';
import { BarcodeFormat, DecodeHintType } from '@zxing/library';
window.BrowserMultiFormatReader = BrowserMultiFormatReader;
window.ZXingBarcodeFormat = BarcodeFormat;
window.ZXingDecodeHintType = DecodeHintType;

// Web-Push – setzt window.appPush
import './push';
