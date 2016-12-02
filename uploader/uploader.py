import pynstagram
import logging
import os

class Uploader:
    """
    Upload Flickr images to Instagram
    """

    def __init__(self, config):
        """
        Constructor
        """
        logging.basicConfig(filename='example.log', filemode='w', level=logging.DEBUG)
        logging.info("init!")

        # save reference to config values passed in
        self.config = config

        self.setup()

        logging.debug("upload starting")

        files = self.find_files()

        if (files):
            self.upload_files(files)

        logging.debug("upload finished")

    def setup(self):
        """
        Initial setup
        """
        self.src_path = self.config.files["path"]

    def find_files(self):
        """
        Find files
        """
        files = []

        # find all files on device
        if os.path.isdir(self.src_path):
            for file in os.listdir(self.src_path):
                if os.path.isfile(self.src_path + file):
                    logging.debug("checking file: {0}".format(file))
                    files.append(file)

            # return array of files if found
            if len(files) > 0:
                logging.debug("{0} new files found".format(len(files)))
                return files
            else:
                logging.debug("no new files found")
        else:
            logging.debug("path not found: {0}".format(self.src_path))


    def upload_files(self, files):
        """
        upload all
        """
        for file in files:
            description = '\nKalle: nice hat 1\n\nTaken on September 17, 2006\n\n#kalle #drunk #tonic #niceHat'
            with pynstagram.client(self.config.instagram["username"], self.config.instagram["password"]) as client:
               client.upload(self.src_path + file, description)
            logging.debug("uploading: " + description)
