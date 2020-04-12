const { IgApiClient } = require('instagram-private-api');
const { appendFile, readFile, writeFile } = require('fs');
const { promisify } = require('util');

const readFileAsync = promisify(readFile);
const appendFileAsync = promisify(appendFile);
const writeFileAsync = promisify(writeFile);
const ig = new IgApiClient();

const lastImageNumberFile = `${__dirname}/../data/uploader-last-image.txt`;
const logFile = `${__dirname}/../data/log.txt`;

(async () => {
  const nextImageNumber = await getNextImageNumber();

  const data = await getData(nextImageNumber);
  if (!data) return;

  const image = getImage(data);
  const type = getType(data);
  const caption = getCaption(data);

  if (image) {
    try {

      await login();

      if (type === 'photo') {
        await uploadImage(image, caption);
        await log(`${nextImageNumber} - image uploaded`);
      } else if (type === 'video') {
        await uploadVideo(image, caption);
        await log(`${nextImageNumber} - video uploaded`);
      }

      await incrementImageNumber(nextImageNumber);
    } catch (e) {
      await log(e.message);
    }
  }
})();

async function login() {
  const config = require(`${__dirname}/config`);
  ig.state.generateDevice(config.username);
  // ig.state.proxyUrl = config.proxy;
  await ig.account.login(config.username, config.password);
}

async function getNextImageNumber() {
  try {
    const imageNumber = await readFileAsync(lastImageNumberFile);
    if (!imageNumber) {
      return 1;
    } else {
      return parseInt(imageNumber, 10) + 1;
    }
  } catch (e) {
    log(e.message);
    return 1;
  }
}

async function getData(nextImageNumber) {
  const filePath = `${__dirname}/../files/${nextImageNumber}.json`;
  try {
    const data = await readFileAsync(filePath);
    if (!data) return;
    return JSON.parse(data);
  } catch (e) {
    return;
  }
}

function getImage(data) {
  if (data.img) {
    return data.img;
  }
  return;
}

function getType(data) {
  if (data.sizes) {
    return data.sizes.media;
  }
  return;
}

function getCaption(data) {
  let caption = "";

  if (data.info.title) {
    caption += "\n" + data.info.title + "\n";
  }
  if (data.info.description) {
    caption += "\n" + data.info.description + "\n";
  }
  if (data.info.date) {
    caption += "\nPhoto taken on " + data.info.date + "\n";
  }

  caption += "\n";

  if (data.exif) {

    if (data.exif.Model && data.exif.Make) {
      if (data.exif.Model.includes(data.exif.Make)) {
        caption += "\nCamera: " + data.exif.Model;
      } else {
        caption += "\nCamera: " + data.exif.Make + " " + data.exif.Model;
      }
    }

    if (data.exif["Lens Model"]) {
      caption += "\nLens: " + data.exif["Lens Model"];
    }
    if (data.exif["Exposure"]) {
      caption += "\nExposure: " + data.exif["Exposure"];
    }
    if (data.exif["Aperture"]) {
      caption += "\nAperture: " + data.exif["Aperture"];
    }
    if (data.exif["Focal Length"]) {
      caption += "\nFocal Length: " + data.exif["Focal Length"];
    }
    if (data.exif["ISO Speed"]) {
      caption += "\nISO Speed: " + data.exif["ISO Speed"];
    }

    caption += "\n";
  }

  if (data.info.albums && data.info.albums.length > 0) {
    caption += "\nAlbums: " + data.info.albums;
  }

  if (data.info.tags && data.info.tags.length > 0) {
    caption += "\n\nTags: " + data.info.tags;
  }

  return caption.trim();
}

async function uploadImage(image, caption) {
  const publishResult = await ig.publish.photo({
    file: await readFileAsync(image),
    caption: caption,
  });
  return publishResult;
}

async function uploadVideo(image, caption) {
  const publishResult = await ig.publish.video({
    video: await readFileAsync(image),
    caption: caption,
  });
  return publishResult;
}

async function incrementImageNumber(nextImageNumber) {
  await writeFileAsync(lastImageNumberFile, nextImageNumber);
}

async function log(message) {
  const date = new Date();
  const formattedMessage = `${date.toString()}: ${message}\n`;
  await appendFileAsync(logFile, formattedMessage);
  console.log(formattedMessage);
}